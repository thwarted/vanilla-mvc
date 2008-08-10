<?php
/* Copyright 2005-2008 Andrew A. Bakun
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

# TODO add support for ignoring tables

class ObjectCache {
    static private $cache = array();
    static public $active = true;

    public static function singleton($o, $id=NULL, $class=NULL) {
        if (!self::$active) return $o;
        if (!is_object($o) || !($o instanceof Model)) {
            throw new Exception("trying to get singleton of non-Model instance");
        }
        if (!isset($class)) {
            $class = get_class($o);
        }
        if (Model::_has_complex_primary_key($class)) {
            return $o;
        }
        if (!isset($id)) {
            $PK = Model::_simple_primary_key($o);
            $id = $o->$PK;
        }
        if ($x = ObjectCache::get($class, $id)) {
            return $x;
        } else {
            ObjectCache::store($class, $id, $o);
            return $o;
        }
    }

    public static function store($class, $pkvalue, $o) {
        if (self::$active) {
            self::$cache[$class][$pkvalue] = $o;
        }
    }

    public static function get($class, $pkvalue) {
        if (self::$active) {
            if (isset(self::$cache[$class][$pkvalue])) {
                $o = self::$cache[$class][$pkvalue];
                return $o;
            }
        }
        return NULL;
    }

    public static function forget($class=NULL, $pkvalue=NULL) {
        if (isset($class)) {
            if (isset($pkvalue)) {
                unset(self::$cache[$class][$pkvalue]);
            } else {
                unset(self::$cache[$class]);
            }
        } else {
            # try to avoid PHP memory leak by attempting
            # to break circular references
            $ak = array_keys(self::$cache);
            foreach ($ak as $class) {
                $pk = array_keys(self::$cache[$class]);
                foreach ($pk as $pkvalue) {
                    unset(self::$cache[$class][$pkvalue]);
                }
                unset(self::$cache[$class]);
            }
        }
    }

    public static function xflush() {
        error_log("called ::xflush, deprecated");
        return self::forget();
    }
}

define('MODELForeignRefFindByPrimaryKey', 1);
define('MODELForeignRefFindByQuery', 2);
define('MODELForeignRefFindByRelation', 3);
define('MODELForeignRefFindByRelationSingle', 4);

class ModelDatabaseSetup {
    static public $TABLEPREFIX = '';
    static private $MODELINFO = array();
    static private $RETABLES = '';
    static private $DBH = NULL;

    static public function modelinfo($model) {
        if (is_object($model)) $model = get_class($model);
        return @ self::$MODELINFO[$model];
    }

    static public function modeldbh($model=NULL) {
        return self::$DBH;
    }

    static public function init_loadcache($cache=NULL, $prefix='', $dbh=NULL) {
        self::$TABLEPREFIX = $prefix;
        self::$DBH = $dbh;
        self::$MODELINFO = $cache;
        $allmodels = array_keys(self::$MODELINFO);
        self::$RETABLES = sprintf('/^((\w+_)??(%s))_id$/', join('|', $allmodels));
    }

    static public function init_examine($tables=array(), $prefix='', $dbh=NULL) {
        self::$TABLEPREFIX = $prefix;
        self::$DBH = $dbh;

        $prefixre = sprintf('/^%s(.+)$/', $prefix);
        foreach ($tables as $tablename) {
            if (preg_match($prefixre, $tablename, $m)) {
                $modelname = $m[1];
                self::$MODELINFO[$modelname] = self::_get_table_definition_for($modelname, $tablename);
            }
        }
        $allmodels = array_keys(self::$MODELINFO);
        self::$RETABLES = sprintf('/^((\w+_)??(%s))_id$/', join('|', $allmodels));

        foreach ($allmodels as $modelname) {
            foreach (self::$MODELINFO[$modelname]['columns'] as $columninfo) {
                self::_check_direct_foreign_reference($modelname, $columninfo);
            }
        }
        foreach ($allmodels as $modelname) {
            foreach (self::$MODELINFO[$modelname]['columns'] as $columninfo) {
                self::_check_through_foreign_reference($modelname, $columninfo);
            }
        }
        foreach ($allmodels as $modelname) {
            foreach (self::$MODELINFO[$modelname]['columns'] as $columninfo) {
                self::_check_backward_foreign_references($modelname, $columninfo);
            }
        }
    }

    static public function init_create_models() {
        $ModelCommonCode = file_get_contents('vanilla/dbmodels/3/Model3CommonCode.php');
        $ModelCommonCode = preg_replace('/^\s+#.*$/m', '', $ModelCommonCode);
        $ModelCommonCode = preg_replace('/^\s*\n/', '', $ModelCommonCode);
        $models = array_keys(self::$MODELINFO);
        foreach ($models as $modelname) {
            if (!class_exists($modelname)) {
                $code = 'class '.$modelname." extends Model {\n".$ModelCommonCode."\n}";
                eval($code);
                self::$MODELINFO[$modelname]['autobuilt'] = true;
            }
        }
    }

    static public function init_create_cachefile() {
        $ModelCommonCode = file_get_contents('vanilla/dbmodels/3/Model3CommonCode.php');
        $ModelCommonCode = preg_replace('/^\s+#.*$/m', '', $ModelCommonCode);
        $ModelCommonCode = preg_replace('/^\s*\n/', '', $ModelCommonCode);
        $v = '<'."?php\n\n".'$_x = '.var_export(self::$MODELINFO, true).";\n";
        $v .= "Model::init_load_cache(\$_x);\nunset(\$_x);\n";
        foreach (self::$MODELINFO as $modelname=>$ti) {
            if (isset($ti['autobuilt']) && $ti['autobuilt']) {
                $code = 'class '.$modelname." extends Model {\n".$ModelCommonCode."}\n";
                $v .= $code;
            }
        }
        return $v;
    }

    static public function _has_complex_primary_key($x) {
        if (is_string($x)) {
            $ti = self::$MODELINFO[$x];
        } else {
            $ti = $x;
        }
        if (count($ti['primarykey']) == 1) return false;
        return true;
    }

    public function _simple_primary_key($x=NULL) {
        if (!isset($x)) $x = $this;
        $ti = self::modelinfo($x);
        $thispk = $ti['primarykey'];
        $thispk = array_shift($thispk);
        return $thispk;
    }

    static private function _check_direct_foreign_reference($modelname, $columninfo) {
        #error_log("looking for direct foreign references for $modelname.".$columninfo['Field']);
        if (!preg_match(self::$RETABLES, $columninfo['Field'], $m)) {
            return;
        }
        list($junk, $virtualname, $junk, $foreigntab) = $m;
        if ($foreigntab == $modelname) {
            # can't reference ourself, there would be confusion 
            # over the virtual field names and single-vs-multiple 
            # values with the forward and back references
            return;
        }
        if (!isset(self::$MODELINFO[$foreigntab])) return;
        # FIXME we only support a single primary key on the foriegn table,
        #       add a check for it

        #error_log(" + joining $modelname.$virtualname to $foreigntab via $modelname.".$columninfo['Field']);
        $vi = array(
            'type'=>MODELForeignRefFindByPrimaryKey,
            'model'=>$foreigntab,
            'sourcecolumn'=>$columninfo['Field'], # key off primary key
        );
        self::$MODELINFO[$modelname]['virtuals'][$virtualname] = $vi;
    }

    static private function _check_through_foreign_reference($modelname, $columninfo) {
        #error_log("looking for intermediary table for $modelname.".$columninfo['Field']);
        $ti = self::$MODELINFO[$modelname];
        if (self::_has_complex_primary_key($ti)) {
            # more than one column is a primary key; 
            # we don't support through foreign references with more than one 
            # column as the primary key
            return;
        }

        $thispk = $ti['primarykey'];
        $thispk = array_shift($thispk);

        $k = array_keys(self::$MODELINFO);
        foreach ($k as $othermodel) {
            #error_log(" - examining $othermodel");
            $oi = @ self::$MODELINFO[$othermodel];
            if (isset($oi)) {
                if (self::_has_complex_primary_key($oi)) {
                    # more than one column is a primary key in the other table
                    # we don't support through foreign references with more than one 
                    # column as the primary key
                    continue;
                }
                $otherpk = $oi['primarykey'];
                $otherpk = array_shift($otherpk);

                if (isset($ti['virtuals'][$oi['model']])) {
                    # we already have a virtual column named after the other model, 
                    # so we can't create a new virtual column, the one that
                    # already exists takes precedent
                    #error_log(" ! virtual column ".$oi['model']." already exists on $modelname, skipping");
                    continue;
                }

                $o = array(
                    sprintf('%s_%s', $ti['model'], $oi['model']),
                    sprintf('%s_%s', $oi['model'], $ti['model']),
                );
                foreach ($o as $thrutbname) {
                    $thruti = @ self::$MODELINFO[$thrutbname];
                    if (!$thruti) continue;

                    #error_log(" + found $thrutbname which maps ".$ti['model']." to ".$oi['model']);
                    $refme = sprintf('%s_id', $ti['model']);
                    $refother = sprintf('%s_id', $oi['model']);
                    if (isset($thruti['columns'][$refme]) &&
                        isset($thruti['columns'][$refother]) &&
                        isset($oi['primarykey']['id'])
                       ) {
                        $q = sprintf('select a.* from %s a left join %s b on a.%s = b.%s where b.%s = ?:id',
                                $oi['tableQ'],
                                $thruti['tableQ'],
                                $oi['columns'][$otherpk]['nameQ'],
                                $thruti['columns'][$refother]['nameQ'],
                                $thruti['columns'][$refme]['nameQ']
                            );
                        $vi = array(
                            'type'=>MODELForeignRefFindByQuery,
                            'model'=>$oi['model'],
                            'query'=>$q,
                            'sourcecolumn'=>$thispk
                        );
                        self::$MODELINFO[$modelname]['virtuals'][$oi['model']] = $vi;
                    }
                }
            }
        }
    }

    static private function _check_backward_foreign_references($modelname, $columninfo) {
        #error_log("looking for backward references based on $modelname.".$columninfo['Field']);
        #print self::$RETABLES."\n";
        #print $columninfo['Field']."\n";
        if (!preg_match(self::$RETABLES, $columninfo['Field'], $m)) {
            return;
        }
        list($junk, $x, $more, $foreigntab) = $m;
        if ($more || $x != $foreigntab) { 
            # this column is x_modelname_id, not modelname_id
            # which we don't support the former, since there is no table named
            # x_modelname, and we'll be adding a virtual column to x_modelname
            return;
        }
        if ($foreigntab == $modelname) {
            # can't reference ourself, there would be confusion 
            # over the virtual field names and single-vs-multiple 
            # values with the forward and back references
            return;
        }
        if (!isset(self::$MODELINFO[$foreigntab])) return;

        if (isset(self::$MODELINFO[$foreigntab]['columns'][$modelname.'_id']) ||
            isset(self::$MODELINFO[$foreigntab]['virtuals'][$modelname])) {
            # virtual column already exists with this name
            return;
        }

        $ftpk = self::$MODELINFO[$foreigntab]['primarykey'];
        $ftpk = array_shift($ftpk);
        #error_log(" + adding virtual column $foreigntab.$modelname based on $modelname.".$columninfo['Field']);
        if ($columninfo['pk']) {
            # if this column is the primary key of this table and we are
            # referring to another table then we can only refer to a single
            # row in the other table.  in that case, we map to a single
            # object rather than an array of objects
            $vi = array(
                'type'=>MODELForeignRefFindByRelationSingle,
                'model'=>$modelname,
                'foreigncolumn'=>$columninfo['Field'],
                'sourcecolumn'=>$ftpk
            );
        } else {
            $vi = array(
                # note that these values are from the context of $foreigntab
                'type'=>MODELForeignRefFindByRelation,
                'model'=>$modelname,
                'foreigncolumn'=>$columninfo['Field'],
                'sourcecolumn'=>$ftpk
            );
        }
        self::$MODELINFO[$foreigntab]['virtuals'][$modelname] = $vi;
    }

    static private function _get_table_definition_for($model, $tablename) {
        #error_log("reading table definition for $model");
        #lib::log_callstack();
        $i = array();
        $i['model'] = $model;
        $i['table'] = $tablename;
        $i['tableQ'] = self::$DBH->quote_label($tablename);
        $columnlist = self::$DBH->table_info($i['table']);
        if (!$columnlist) {
            error_log("table ".$i['table']." does not exist");
            #self::$MODELINFO[$model] = false;
            return false;
        }

        $i['has_auto_incr'] = false;
        $cols = array();
        $pk = array();
        foreach ($columnlist as $ci) {
            $ci['nameQ'] = self::$DBH->quote_label($ci['Field']);
            $cols[$ci['Field']] = $ci;
            if ($ci['Key'] === 'PRI') {
                $pk[$ci['Field']] = $ci['Field'];
                # FIXME Extra is not portable
                if (preg_match('/auto_increment/', $ci['Extra'])) {
                    $i['has_auto_incr'] = true;
                }
            }
        }
        $i['columns'] = $cols;
        $i['primarykey'] = $pk;
        # assign what we have so far, the physical description of the table/model,
        # because we will recurse if we find a column that references another table
        # and processing that table may require reading our physical columns
        # (but not our virtual columns)
        $i['virtuals'] = array(); # TEMP
        return $i;
    }
}

class ModelDataQuery extends ModelDatabaseSetup {
    protected $_Query_bound_to_row = false;

    private static function _condition_integer($tableinfo, $cond) {
        # simple integer key
        $a = array('id'=>$cond);
        # FIXME assumes a single column is the primary surrogate key
        $tableQ = $tableinfo['tableQ'];
        $pk = self::_simple_primary_key($tableinfo['model']);
        $pkQ = $tableinfo['columns'][$pk]['nameQ'];
        $where = sprintf('%s.%s = ?:key', $tableinfo['tableQ'], $pkQ);
        return array($where, $a);
    }

    private static function _condition_string($tableinfo, $cond) {
        $a = array('key'=>$cond);
        # FIXME assumes a single column is the primary surrogate key
        $tableQ = $tableinfo['tableQ'];
        $pk = self::_simple_primary_key($tableinfo['model']);
        $pkQ = $tableinfo['columns'][$pk]['nameQ'];
        $where = sprintf('%s.%s = ?:key', $tableinfo['tableQ'], $pkQ);
        return array($where, $a);
    }

    private static function _condition_array($tableinfo, $cond) {
        # where clause and an array of substitutions
        if (count($cond) == 2 && isset($cond[0]) && isset($cond[1]) && is_string($cond[0]) && is_array($cond[1])) {
            $where = $cond[0];
            $a = $cond[1];
        } else {
            $a = array();
            $where = array();
            # $f could be an SQL expression, don't quote it
            foreach ($cond as $f=>$v) {
                $x = uniqid();
                if (preg_match('/^\w+$/', $f)) {
                    $f = self::modeldbh($tableinfo['model'])->quote_label($f);
                }
                if (is_array($v)) {
                    $x .= ":join";
                    $where[] = sprintf('%s in (?:%s)', $f, $x);
                    $a[$x] = $v;
                } elseif (is_object($v) && $v instanceof cond) {
                    list($expr, $vs) = $v->expr();
                    $where[] = sprintf('%s %s', $f, $expr);
                    foreach ($vs as $k=>$x) {
                        $a[$k] = $x;
                    }
                } else {
                    $where[] = sprintf('%s = ?:%s', $f, $x);
                    $a[$x] = $v;
                }
            }
            $where = join(' and ', $where);
        }
        return array($where, $a);
    }

    public static function _conditions_to_query($tableinfo, $cond) {
        if (strval(intval($cond)) === "$cond") {
            return self::_condition_integer($tableinfo, $cond);
        } elseif (is_string($cond)) { # could be a string primary key (uuid)
            return self::_condition_string($tableinfo, $cond);
        } elseif (is_array($cond)) {
            return self::_condition_array($tableinfo, $cond);
        } else {
            throw new Exception("illegal type for conditions to ".$tableinfo['model']."::find");
        }
    }

    public static function _find($model, $a) {
        $cond = array_shift($a);
        if (empty($cond)) return array();

        $ti = self::modelinfo($model);
        if (!$ti) return NULL;
        $limit = array_shift($a);
        if (!empty($limit) && !preg_match('/^\s*\d+\s*(,\s*\d+)?\s*$/', $limit)) {
            # FIXME report a better error here
            error_log("invalid limit $limit");
            return array();
        }
        $orderby = array_shift($a);

        list($where, $a) = self::_conditions_to_query($ti, $cond);

        if (preg_match('/^\s*select/', $where)) {
            $q = $where;
        } else {
            $q = "select * from ".$ti['tableQ']." where $where";
        }
        if ($orderby) {
            # FIXME protect against $orderby being possibly tainted
            $q .= " order by ".$orderby;
        }
        if ($limit) {
            $q .= " limit $limit";
        }
        $sth = self::modeldbh($model)->prepare($q);
        $sth->execute($a);
        #error_log($sth->_stmt());
        $r = array();
        while($o = $sth->fetchrow_object($model)) {
            $o = ObjectCache::singleton($o);
            $o->checkpoint();
            $o->_Query_bound_to_row = true;
            $r[] = $o;
        }
        return $r;
    }

    public static function _find_first($model, $a) {
        $a = array($a[0], 1);
        $x = self::_find($model, $a);
        if (empty($x)) return NULL;
        return array_shift($x);
    }

    # following functions figure out which model should be used
    # god, PHP's object model is awful
    public static function find() {
        $modelclass = self::_find_static_invoking_class();
        $a = func_get_args();
        return self::_find($modelclass, $a);
    }

    public static function find_first() {
        $modelclass = self::_find_static_invoking_class();
        $a = func_get_args();
        return self::_find_first($modelclass, $a);
    }

    # oh my fucking god
    # compile-time resolution of static calls loses the class the method was invoked on
    # and apparently this was on purpose!?!
    # http://bugs.php.net/bug.php?id=12622
    #
    # search up the call stack
    private static function _find_static_invoking_class() {
        static $hist = array();
        if (get_class() === __CLASS__) {
            $db = debug_backtrace();
            $frame = $db[1];
            $k = $frame['file'].':'.$frame['line'];
            if (isset($hist[$k])) {
                return $hist[$k];
            }
            $x = file($frame['file']);
            $line = $x[$frame['line']-1];
            $method = $frame['function'];

            if (preg_match('/::'.$method.'.+::'.$method.'/', $line)) {
                throw new Exception(sprintf("%s:%s contains multiple static calls to $method, unable to resolve static method invocation",
                    $frame['file'], $frame['line']));
            }
            if (preg_match($rx='/\b(\w+)::'.$method.'\(/', $line, $m)) {
                $target = $m[1];
                if ($target === 'parent' || $target === 'self') {
                    # the actually call is through parent::find
                    # so search backward through the file looking for a class XXX line
                    # thankfully, PHP doesn't allow nested classes
                    # nor does it allow include/require to BUILD class definitions
                    # (since include/require are run-time evaluated), so this
                    # should be pretty reliable.
                    $target = false;
                    for($c = $frame['line']-1; $c > 0; $c--) {
                        if (preg_match('/class\s+(\w+)(\s+extends\s+(\w+)\s*|\s*)\{/', $x[$c], $m)) {
                            $target = $m[1];
                            break;
                        }
                    }
                    if (empty($target)) {
                        throw new Exception(sprintf("unable to determine defining class for parent::%s call on %s:%s",
                            $method, $frame['file'], $frame['line']));
                    }
                }
                $hist[$k] = $target;
                return $target;
            }
            # should never get here
            throw new Exception(sprintf('unable to determine static invocation on %s:%s', $frame['file'], $frame['line']));
        } else {
            return get_class();
        }
    }

}

class ModelDataManipulation extends ModelDataQuery {
    private $_Manipulation_real_columns = array();
    private $_Manipulation_virt_columns = array();
    private $_Manipulation_extra_columns = array();
    private $_Manipulation_originals = array();
    private $_Manipulation_dirty = false;

    public function natural_members() {
        $ti = self::modelinfo($this);
        return array_keys($ti['columns']);
    }

    public function virtual_members() {
        $ti = self::modelinfo($this);
        return array_keys($ti['virtuals']);
    }

    public function generated_members() {
        static $cache = array();
        $class = get_class($this);
        if (isset($cache[$class])) {
            return $cache[$class];
        }
        $mall = get_class_methods($this);
        $mgen = array();
        foreach ($mall as $x) {
            if (preg_match('/^__get_(\w+)/', $x, $m)) {
                $mgen[] = $m[1];
            }
        }
        sort($mgen);
        $cache[$class] = $mgen;
        return $mgen;
    }

    public function pp() {
        $ti = self::modelinfo($this);
        if (!$ti) return '';
        $x = array();
        foreach ($ti['primarykey'] as $pk) {
            $x[] = sprintf('%s=%s', $pk, $this->$pk);
        }
        return sprintf('%s(%s)', get_class($this), join(',', $x));
    }

    public function dump() {
        $ti = self::modelinfo($this);
        if (!$ti) return array();
        $r = array();
        #$r['UUID'] = $this->pp();
        foreach ($this->_Manipulation_real_columns as $f=>$v) {
            if (is_object($v)) { # shouldn't happen, fields are plain values
                $v = $v->pp();
            } elseif (is_array($v)) { # shouldn't happen either
                $v = array();
            }
            #$r['fields'][$f] = $v;
            $r[$f] = $v;
        }
        foreach ($this->_Manipulation_extra_columns as $f=>$v) {
            if (is_object($v)) {
                $v = $v->pp();
            } elseif (is_array($v)) {
                $v = count($v).' element array()';
            }
            #$r['extras'][$f] = $v;
            $r[$f] = $v;
        }
        $virts = array_keys($ti['virtuals']);
        foreach ($virts as $f) {
            $v = $this->$f;
            if (is_object($v) && $v instanceof Model) {
                $v = $v->pp();
            } elseif (is_array($v)) {
                $v = count($v).' element array()';
            }
            #$r['virtuals'][$f] = $v;
            $r[$f] = $v;
        }
        return $r;
    }

    public function checkpoint() {
        $this->_Manipulation_originals = $this->_Manipulation_real_columns;
        $this->_Manipulation_dirty = false;
    }

    public function __set($field, $value) {
        $tableinfo = self::modelinfo($this);
        if (!$tableinfo) return;
        if (isset($tableinfo['primarykey'][$field])) {
            if ($this->_Query_bound_to_row) {
                $m = get_class($this)."->$n is not writable, it is a primary key and the object is row-bound";
                throw new Exception($m);
            }
        }
        if (isset($tableinfo['columns'][$field])) {
            $this->_Manipulation_real_columns[$field] = $value;
            $this->_Manipulation_dirty = true;
        } else {
            $this->_Manipulation_extra_columns[$field] = $value;
        }
    }

    public function __get($field) {
        $m = "__get_$field";
        if (is_callable(array($this, $m))) {
            return call_user_func(array($this, $m), $n);
        }

        # literal fields are more likely than virtual or extra 
        # fields, so look for them first
        if (array_key_exists($field, $this->_Manipulation_real_columns)) {
            return $this->_Manipulation_real_columns[$field];
        }
        if (array_key_exists($field, $this->_Manipulation_extra_columns)) {
            return $this->_Manipulation_extra_columns[$field];
        }
        list($found, $v) = $this->_get_virtual($field);
        if ($found) return $v;
        return NULL;
    }

    public function __isset($n) {
        return isset($this->_Manipulation_real_columns[$n]);
    }

    public function __unset($n) {
        # don't use unset(...) here, we want to retain the member
        $this->_Manipulation_real_columns[$n] = NULL;
    }

    public function changed($col) {
        $oldval = @ $this->_Manipulation_originals[$col];
        $newval = @ $this->_Manipulation_real_columns[$col];
        return !($oldval === $newval);
    }

    private function _run_virtual_conditions($vi /*virtual column info*/) {
        switch($vi['type']) {
            case MODELForeignRefFindByPrimaryKey:
                #error_log("doing by pk for ".var_export($vi, true)."<br/>");
                $cond = $this->_Manipulation_real_columns[$vi['sourcecolumn']];
                $value = self::_find_first($vi['model'], array($cond));
                break;

            case MODELForeignRefFindByQuery:
                #error_log("doing by query for ".var_export($vi, true));
                $searchval = $this->_Manipulation_real_columns[$vi['sourcecolumn']];
                $cond = array($vi['query'], array('id'=>$searchval));
                $value = self::_find($vi['model'], array($cond));
                    $c = new ModelCollection($vi['model']);
                    $c->merge($value);
                    $value = $c;
                break;

            case MODELForeignRefFindByRelation:
                #error_log("doing by relation for ".var_export($vi, true)."<br />");
                $searchcol = $vi['foreigncolumn'];
                $searchval = $this->_Manipulation_real_columns[$vi['sourcecolumn']];
                $cond = array($searchcol=>$searchval);
                $value = self::_find($vi['model'], array($cond));
                    $c = new ModelCollection($vi['model']);
                    $c->merge($value);
                    $value = $c;
                break;

            case MODELForeignRefFindByRelationSingle:
                #error_log("doing by single relation for ".var_export($vi, true)."<br />");
                $searchcol = $vi['foreigncolumn'];
                $searchval = $this->_Manipulation_real_columns[$vi['sourcecolumn']];
                $cond = array($searchcol=>$searchval);
                $value = self::_find_first($vi['model'], array($cond));
                break;

            default:
                throw new Exception("unimplemented Model Type value");
        }
        return $value;
    }

    private function _get_virtual($field) {
        if (array_key_exists($field, $this->_Manipulation_virt_columns)) {
            error_log("returning previously set value for virtual $field");
            return array(true, $this->_Manipulation_virt_columns[$field]);
        }

        # are we trying to access a detected virtual column?
        $tableinfo = self::modelinfo($this);
        if (!$tableinfo)
            return array(false, false);
        if (!isset($tableinfo['virtuals'][$field]))
            return array(false, false);

        $vi = $tableinfo['virtuals'][$field];
        if (!array_key_exists($vi['sourcecolumn'], $this->_Manipulation_real_columns)) {
            error_log("sourcecolumn ".get_class($this).".".$vi['sourcecolumn']." does not exist");
            return array(false, false);
        }
        $value = $this->_run_virtual_conditions($vi);
        $this->_Manipulation_virt_columns[$field] = $value;
        return array(true, $value);
    }

    public function save() {
        $ti = self::modelinfo($this);
        if (!$ti) return;

        # buh, this should be atomic; no portable way
        # to grab a database lock though?
        if ($this->_is_new_row($ti)) {
            $this->_save_insert($ti);
        } else {
            $this->_save_update($ti);
        }
        $this->_Query_bound_to_row = true;
        $this->checkpoint();
    }

    private function _build_pkcondition($ti) {
        $c = array();
        $v = array();
        $x = 0;
        foreach ($ti['primarykey'] as $key) {
            $x++;
            $k = "pk$key$x";
            $c[] = sprintf('%s = ?:%s', $ti['columns'][$key]['nameQ'], $k);
            if (isset($this->$key)) {
                $v[$k] = $this->$key;
            } else {
                error_log("pk $key not set");
                return false;
            }
        }
        return array($c, $v);
    }

    private function _is_new_row($ti) {
        $q = "select count(1) from ".$ti['tableQ']." where ";
        $x = $this->_build_pkcondition($ti);
        # one of the fields that is part of the primary
        # key is not set, assume it is a new row
        if (!$x) return true;
        list($c, $v) = $x;
        $q .= join(' and ', $c);
        $sth = self::modeldbh($this)->prepare($q);
        print "$q\n";
        $sth->execute($v);
        list($c) = $sth->fetchrow_array();
        if ($c > 1) throw new Exception("fatal error: more than row matches what we thought was the primary key");
        return !($c);
    }

    private function _save_insert($ti) {
        $setc = array();
        $setv = array();
        $setf = array();
        $x = 0;
        foreach ($ti['columns'] as $col=>$cinfo) {
            $x++;
            $k = "i$col$x";
            $setc[] = $ti['columns'][$col]['nameQ'];
            $setv[$k] = @ $this->_Manipulation_real_columns[$col];
            $setf[] = "?:$k";
        }
        $q = "insert into ".$ti['tableQ'].' ('.join(', ', $setc).') values ('.join(', ', $setf).')';
        print "$q\n";
        print_r($setv);

        $sth = self::modeldbh($this)->prepare($q);
        $sth->execute($setv);

        if ($ti['has_auto_incr'] && !self::_has_complex_primary_key($ti)) {
            $pk = $ti['primarykey'];
            $pk = array_shift($pk);
            $this->$pk = $sth->insert_id();
        }
    }

    private function _save_update($ti) {
        $setc = array();
        $setv = array();
        $x = 0;
        foreach ($ti['columns'] as $col=>$cinfo) {
            if ($cinfo['Key'] === 'PRI') continue;
            if ($this->changed($col)) continue;
            $newval = @ $this->_Manipulation_real_columns[$col];
            $x++;
            $k = "c$col$x";
            $setc[] = sprintf('%s = ?:%s', $ti['columns'][$col]['nameQ'], $k);
            $setv[$k] = $newval;
        }
        if (!$setc) return;
        $q = "update ".$ti['tableQ'].' set '.join(", ", $setc);
        list($c, $v) = $this->_build_pkcondition($ti);
        $q .= " where ".join(" and ", $c);
        $setv = array_merge($setv, $v);
        print "$q\n";
        print_r($setv);
        $sth = self::modeldbh($this)->prepare($q);
        $sth->execute($setv);
    }
}


class Model3 extends ModelDataManipulation { }



