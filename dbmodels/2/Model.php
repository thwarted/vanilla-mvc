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

class Model {
    static public $TABLEPREFIX = '';
    static private $MODELINFO = array();
    static private $RETABLES = '';
    static private $DBH = NULL;

    private $_fields;
    private $_virtuals; # demand-populated foreign relations
    private $_bound_to_row = false;
    private $_originals;
    private $_dirty = false;

    static public function init($tables=array(), $prefix='', $dbh=NULL) {
        self::$TABLEPREFIX = $prefix;
        foreach ($tables as $t) {
            if (self::$TABLEPREFIX) {
                if (preg_match('/^'.self::$TABLEPREFIX.'(.+)$/', $t, $m)) {
                    $t = $m[1];
                    self::$MODELINFO[$t] = NULL;
                }
            } else {
                self::$MODELINFO[$t] = NULL;
            }
        }
        self::$RETABLES = sprintf('/^((\w+_)??(%s))_id$/', join('|', array_keys(self::$MODELINFO)));
        self::$DBH = $dbh;
    }

    public function __construct($data=array()) {
        $this->_fields = $data;
        $this->_virtuals = array();
        $this->_dirty = false;
        $this->_bound_to_row = false;
    }

    public function checkpoint() {
        $this->_originals = $this->_fields;
        $this->_dirty = false;
    }

    protected function _bound_to_row($mode=true) {
        $this->_bound_to_row = $mode;
    }

#####################################################################
    public function generated_fields($model=NULL) {
        if (isset($model)) {
            return self::_generated_fields($model);
        }
        return self::_generated_fields(get_class($this));
    }

    public function virtual_fields($model=NULL) {
        if (isset($model)) {
            return self::_virtual_fields($model);
        }
        return self::_virtual_fields(get_class($this));
    }

    public function pp() {
        $ti = self::_modelinfo($this);
        if (!$ti) return '';
        $x = array();
        foreach ($ti['primarykey'] as $pk) {
            $x[] = sprintf('%s=%s', $pk, $this->$pk);
        }
        return sprintf('%s(%s)', get_class($this), join(',', $x));
    }

    public function dump() {
        $ti = self::_modelinfo($this);
        if (!$ti) return array();
        $r = array();
        #$r['model'] = get_class($this);
        $seen = array();
        foreach ($ti['columns'] as $c=>$colinfo) {
            if (is_object($this->_fields[$c])) { # shouldn't happen, fields are plain values
                $v = "object(".get_class($this->_fields[$c]).")";
            } elseif (is_array($this->_fields[$c])) {
                $v = array();
            } else {
                $v = $this->_fields[$c];
            }
            #$r['fields'][$c] = $v;
            $r[$c] = $v;
            $seen[$c] = true;
        }
        foreach ($this->_virtuals as $vn=>$v) {
            if (is_object($v)) {
                if ( ($v instanceof ModelCollection) ) {
                    $v = $v->dump();
                } elseif ($v instanceof Model) {
                    $v = $v->pp();
                } else {
                    $v = "object(".get_class($v).")";
                }
            }
            #$r['virtual'][$vn] = $v;
            $r[$vn] = $v;
        }
        /*
        foreach ($ti['virtuals'] as $c=>$virtinfo) {
            $v = $this->$c; # virtual column doesn't exist until we request it
            if (is_object($v)) {
                if (! ($v instanceof ModelCollection) ) {
                    $v = "collection(".get_class($v).")";
                } elseif ($v instanceof Model) {
                    $v = $v->pp();
                } else {
                    $v = "object(".get_class($v).")";
                }
            }
            #$r['virtual'][$c] = $v;
            $r[$c] = $v;
        }
        */
        foreach ($this->_fields as $c=>$v) {
            if (!isset($seen[$c])) {
                #$r['extra'][$c] = $v;
                $r[$c] = $v;
            }
        }
        return $r;
    }

#####################################################################
    public function __set($n, $value) {
        $tableinfo = self::_modelinfo($this);
        if (!$tableinfo) return;
        if (isset($tableinfo['primarykey'][$n])) {
            if ($this->_bound_to_row) {
                $m = get_class($this)."->$n is not writable, it is a primary key and the object is row-bound";
                throw new Exception($m);
            }
        }
        $this->_fields[$n] = $value;
        if (isset($tableinfo['columns'][$n])) {
            $this->_dirty = true;
        }
    }

    public function __get($n) {
        $m = "__get_$n";
        # call the override, if it exists
        if (is_callable(array($this, $m))) {
            return call_user_func(array($this, $m), $n);
        }

        # literal fields are more likely than virtual fields
        # so look for them first
        if (array_key_exists($n, $this->_fields)) {
            return $this->_fields[$n];
        }

        list($found, $v) = $this->_get_virtual($n);
        if ($found) return $v;

        return NULL;
    }

    private function _get_virtual($n) {
        $sourcefield = $n."_id";
        if (array_key_exists($n, $this->_virtuals)) {
            #error_log("returning previously set value for virtual $n");
            return array(true, $this->_virtuals[$n]);
        }

        # first, find out if this table references another table 
        # through an *_id column
        # user_id          => user
        # postedby_user_id => user
        # matches a single row, returns one object
        #error_log("checking for $sourcefield");
        if (array_key_exists($sourcefield, $this->_fields)) {
            #error_log("$sourcefield found");
            if (preg_match(self::$RETABLES, $sourcefield, $m)) {
                #lib::el($m);
                list($junk, $virtual, $junk, $foreigntab) = $m;
                # $virtual should equal $n at this point
                if ($foreigntab != get_class($this)) {
                    # can't reference ourself, there would be confusion 
                    # over the virtual field names and single-vs-multiple 
                    # values with the forward and back references
                    $fi = self::_modelinfo($foreigntab);
                    if ($fi) {
                        #error_log("joining ".get_class($this).".$sourcefield to $foreigntab");

                        # populate the virutals list for the model
                        # not the best place to do this
                        self::$MODELINFO[$foreigntab]['virtuals'][$foreigntab] = true; # TEMP, for testing

                        $v = self::_find_first($foreigntab, array($this->$sourcefield));
                        $this->_virtuals[$n] = $v;
                        return array(true, $v);
                    }
                }
            }
        }

        $ti = self::_modelinfo($this);
        $oi = self::_modelinfo($n);
        #error_log("looking for intermediary table");
        if ($oi && count($ti['primarykey']) == 1) {
            # now, try to find an intermedary table that joins us to another table
            # it's going to be named either $n_$this or $this_$n
            # and it will contain a reference to this table
            # and a reference to the model named $n
            # returns an array of objects, could be a many-to-many mapping
            $o = array(
                sprintf('%s_%s', $ti['model'], $n),
                sprintf('%s_%s', $n, $ti['model']),
            );
            foreach ($o as $thrutbname) {
                $thruti = self::_modelinfo($thrutbname);
                if (!$thruti) continue;

                error_log("found $thrutbname");
                $refme = sprintf('%s_id', $ti['model']);
                $refother = sprintf('%s_id', $oi['model']);
                if (isset($thruti['columns'][$refme]) && isset($thruti['columns'][$refother])) {
                    #                oi                thruti             oi x   thruti   x       thruti  x
                    #select a.* from image a left join product_image b on a.id = b.image_id where b.product_id = 10;

                    $q = sprintf('select a.* from %s a left join %s b on a.%s = b.%s where b.%s = ?:id',
                        $oi['tableQ'],
                        $thruti['tableQ'],
                        $oi['primarykey']['id'],
                        $refother,
                        $refme
                    );
                    error_log($q);
                    $v = self::_find($n, array(array($q, array('id'=>$this->id))));
                    $x = new ModelCollection($n);
                    $x->merge($v);
                    $this->_virtuals[$n] = $x;
                    return array(true, $x);
                }
            }
        }

        # otherwise, the other table may reference this one by having a column
        # that matches *_THISMODEL_id
        # returns an array
        #if (preg_match(self::$RETABLES, $ci['Field'], $m)) {

        return array(false, false);


        #$ti = self::_modelinfo($n);
        #if (!$ti) return array(false, false);

        /*
        # resolve a virtual column
        $ti = self::_modelinfo($this);
        if (!$ti) return NULL;
        if (isset($ti['virtuals'][$n])) {
            if (!isset($this->_virtuals[$n])) {
                error_log("getting value for $n");
                $fkmodel = $ti['virtuals'][$n]['fkmodel'];
                $sourcefield = $ti['virtuals'][$n]['sourcefield'];
                $this->_virtuals[$n] = self::_find($fkmodel, array($this->$sourcefield));
            }
            return $this->_virtuals[$n];
        }
        */
    }

    public function __isset($n) {
        return isset($this->_fields[$n]);
    }

    public function __unset($n) {
        # don't use unset(...) here, we want to retain the member
        $this->_fields[$n] = NULL;
        #unset($this->_fields[$n]);
    }

    public function changed($col) {
        $oldval = @ $this->_originals[$col];
        $newval = @ $this->_fields[$col];
        return !($oldval === $newval);
    }

#####################################################################
    public function save() {
        $ti = self::_modelinfo($this);
        if (!$ti) return;
        # TODO should use _bound_to_row here to safeguard
        # against replacing a row with a newly created object that 
        # was assigned the same primary key
        if ($this->_is_new_row($ti)) {
            $this->_save_insert($ti);
        } else {
            $this->_save_update($ti);
        }
        $this->_bound_to_row(true);
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
                return false;
            }
        }
        return array($c, $v);
    }

    private function _is_new_row($ti) {
        $q = 'select count(1) from '.$ti['tableQ'].' where ';
        $x = $this->_build_pkcondition($ti);
        # one of the fields that is part of the primary 
        # key is not set, assume it is a new row
        if (!$x) return true;

        list($c, $v) = $x;
        $q .= join(' and ', $c);
        $sth = $ti['dbh']->prepare($q);
        $sth->execute($v);
        list($c) = $sth->fetchrow_array();
        if ($c > 1) throw new Exception("fatal error: more than one row matches primary key");
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
            $setv[$k] = @ $this->_fields[$col];
            $setf[] = "?:$k";
        }
        $q = "insert into ".$ti['tableQ'].' ('.join(', ', $setc).') values ('.join(', ', $setf).')';
        print "$q\n";
        print_r($setv);

        $sth = $ti['dbh']->prepare($q);
        $sth->execute($setv);

        if ($ti['has_auto_incr'] && count($ti['primarykey']) == 1) {
            $pk = $ti['primarykey'][0];
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
            $newval = @ $this->_fields[$col];
            $x++;
            $k = "c$col$x";
            $setc[] = sprintf('set %s = ?:%s', $ti['columns'][$col]['nameQ'], $k);
            $setv[$k] = $newval;
        }
        if (!$setc) return;
        $q = "update ".$ti['tableQ'].' '.join(", ", $setc);
        list($c, $v) = $this->_build_pkcondition($ti);
        $q .= " where ".join(" and ", $c);
        $setv = array_merge($setv, $v);
        print "$q\n";
        print_r($setv);
        $sth = $ti['dbh']->prepare($q);
        $sth->execute($setv);
    }

#####################################################################
    # public because ModelCollection needs to call us
    # consider moving to a ModelBase class
    public static function _modelinfo($model) {
        if (is_object($model)) $model = get_class($model);
        if (!isset(self::$MODELINFO[$model])) {
            #error_log("reading table definition for $model");
            #lib::log_callstack();
            $i = array();
            $i['model'] = $model;
            $i['table'] = self::$TABLEPREFIX . $model;
            $columnlist = self::$DBH->table_info($i['table']);
            if (!$columnlist) {
                error_log("table ".$i['table']." does not exist");
                self::$MODELINFO[$model] = false;
                return false;
            }
            
            $i['tableQ'] = self::$DBH->quote_label(self::$TABLEPREFIX . $model);
            $i['dbh'] = self::$DBH; # may be redundant, since we are only supporting one database
            $i['has_auto_incr'] = false;
            $cols = array();
            $pk = array();
            foreach ($columnlist as $ci) {
                $ci['nameQ'] = $i['dbh']->quote_label($ci['Field']);
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
            self::$MODELINFO[$model] = $i;

            /*
            error_log("processing virtual columns for $model");
            $virts = array();
            foreach (self::$MODELINFO[$model]['columns'] as $cn=>$ci) {
                if (preg_match(self::$RETABLES, $ci['Field'], $m)) {
                    list($junk, $virtual, $junk, $foreigntab) = $m;
                    $referstoforeigntab = false;
                    if (array_key_exists($foreigntab, self::$MODELINFO)) {
                        if ($foreigntab != $model) {
                            error_log("found fk column $virtual");
                            $fmi = self::_modelinfo($foreigntab);
                            if (isset($fmi['columns']['id']) && isset($fmi['primarykey']['id'])) {
                                $referstoforeigntab = $foreigntab;
                            }
                        }
                    }
                    if (!empty($referstoforeigntab)) {
                        $virts[$virtual] = array('fkmodel'=>$referstoforeigntab, 'sourcefield'=>$ci['Field']);
                    }
                }
            }
            self::$MODELINFO[$model]['virtuals'] = $virts;
            */
        }
        return self::$MODELINFO[$model];
    }

    # generate a string that can be used a unique hash index for this object
    private static function _mkpkidentifier($tableinfo, $o) {
        $kv = array();
        foreach ($tableinfo['primarykey'] as $pkc) {
            $kv[] = $o->$pkc;
        }
        return join("\001", $kv);
    }

    public function pkidentifier() {
        $ti = self::_modelinfo(get_class($this));
        return self::_mkpkidentifier($ti, $this);
    }


    private static function _generated_fields($model) {
        $mall = get_class_methods($model);
        $mgen = array();
        foreach ($mall as $x) {
            if (preg_match('/^__get_(\w+)/', $x, $m))
                $mgen[] = $m[1];
        }
        sort($mgen);
        return $mgen;
    }

    private static function _virtual_fields($model) {
        $ti = self::_modelinfo($model);
        if (!$ti) return array();
        $vf = array_keys($ti['virtuals']);
        sort($vf);
        return $vf;
    }

#####################################################################
    private static function _find_first($model, $a) {
        $a = array($a[0], 1); # condition and a limit of 1
        $x = self::_find($model, $a);
        if (empty($x)) return NULL;
        return $x[0];
    }

    private static function _find($model, $a) {
        $ti = self::_modelinfo($model);
        if (!$ti) return NULL;

        $cond = array_shift($a);
        if (empty($cond)) return array();
        $limit = array_shift($a);
        if (!empty($limit) && !preg_match('/^\d+(,\d+)?$/', $limit)) {
            # FIXME report a better error here
            error_log("invalid limit $limit");
            return array();
        }
        $orderby = array_shift($a);

        list($where, $a) = self::_conditions_to_query($ti, $cond);

        if (preg_match('/^\s*select/', $where)) {
            $q = $where;
        } else {
            #$q = "select *, random() as `_myrand` from ".$ti['tableQ']." where $where";
            $q = "select * from ".$ti['tableQ']." where $where";
        }
        if ($orderby) {
            $q .= " order by ".$orderby;
        }
        if ($limit) {
            $q .= " limit $limit";
        }
        $sth = $ti['dbh']->prepare($q);
        $sth->execute($a);
        #error_log($sth->_stmt());
        $r = array();
        while($o = $sth->fetchrow_object($model)) {
            #$pkv = self::_mkpkidentifier($ti, $o);
            #$o = _object_cache::singleton($o, $pkv, $model);
            $o->checkpoint();
            $o->_bound_to_row(true);
            $r[] = $o;
        }
        return $r;

    }

    public function _conditions_to_query($tableinfo, $cond) {
        #$tx = $tableinfo;
        #unset($tx['dbh']);
        #print_r($tx);
        if (strval(intval($cond)) === "$cond") {
            # simple integer key
            $a = array('id'=>$cond);
            # FIXME assumes a single column is the primary surrogate key
            # FIXME should use the first element of primarykey
            # FIXME should use the quoted form of the primary key column
            $where = sprintf('%s.%s = ?:id', $tableinfo['tableQ'], $tableinfo['primarykey']['id']);
        } elseif (is_string($cond)) { # could be a string primary key (uuid)
            $a = array('id'=>$cond);
            # FIXME assumes a single column is the primary surrogate key
            # FIXME should use the first element of primarykey
            # FIXME should use the quoted form of the primary key column
            $where = sprintf('%s.%s = ?:id', $tableinfo['tableQ'], $tableinfo['primarykey']['id']);
        } elseif (is_array($cond)) {
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
                        $f = $tableinfo['dbh']->quote_label($f);
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
        } else {
            throw new Exception("illegal type for conditions to ".$tableinfo['model']."::find");
        }
        return array($where, $a);
    }

#####################################################################

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
            if (isset($hist[$frame['file'].':'.$frame['line']])) {
                return $hist[$frame['file'].':'.$frame['line']];
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
                $hist[$frame['file'].':'.$frame['line']] = $target;
                return $target;
            }
            # should never get here
            throw new Exception(sprintf('unable to determine static invocation on %s:%s', $frame['file'], $frame['line']));
        } else {
            return get_class();
        }
    }

}

