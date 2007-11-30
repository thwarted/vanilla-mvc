<?php
/* Copyright 2005-2007 Andrew A. Bakun
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

$_generation = 0; # used to avoid loops when saving data to the database

class Model {
    protected $_generation;
    protected $_t;
    protected $_db;
    protected $__db;
    protected $__members = array();
    protected $__virtmembers = array();
    protected $__original = array();
    protected $__jointype = NULL;

    public function __construct($data=array()) {
        $this->_t = eval('return '.get_class($this).'::$__table__;');
        $this->_db = $this->_t->db;
        foreach ($data as $f=>$v) {
            $this->$f = $v;
        }
        $this->_generation = 0;
    }

    public function labelx() {
        $pk = $this->_t->pk;
        $pkv = @ $this->__members[$pk];
        if (empty($pkv)) {
            $pkv = '?';
        }
        return sprintf('%s(%s=%s)', get_class($this), $pk, $pkv);
    }

    public function _from_database($x) {
        throw new Exception("don't call _from_database");
        $this->__db = $x;
    }

    public function _refresh() {
        $pk = $this->_t->pk;
        $id = $this->$pk;
        _object_cache::forget(get_class($this), $id);
        $this->__virtmembers = array();
        $this->__members = array();
        $x = $this->_t->find_first($id);  # this will give us a freshly created duplicate
        foreach ($this->_t->columns as $f=>$c) {
            # copy all the fields from the dup to this
            $this->$f = $x->$f;
        }
        _object_cache::forget(get_class($this), $id); # forget the singleton dup
        _object_cache::store(get_class($this), $id, $this); # store this
    }

    public function ___set_virtual($n, $v) {
        # get the primary key for the value we are setting
        $remotetbclass = $this->_t->virtual[$n]->class;
        $otherPK = $this->_db->tables[$remotetbclass]->pk;
        # find out which REAL/non-virtual column we should update with the key value
        $source = $this->_t->virtual[$n]->source;
        if (isset($v) && $v instanceof $remotetbclass) {
            $this->$source = $v->$otherPK;
        } else {
            $this->$source = NULL;
        }
    }

    public function __set($n, $v) {
        $m = "__set_$n";
        if (is_callable(array($this, $m))) {
            return call_user_func(array($this, $m), $v);
        }

        if (isset($this->_t->virtual[$n])) {
            return $this->___set_virtual($n, $v);
        }
        if (is_string($v) && is_numeric($v) && strval(intval($v)) === $v) {
            $v = intval($v);
        }
        $this->__members[$n] = $v;
    }

    public function checkpoint() {
        $this->__original = $this->__members;
    }

    public function ___get_virtual($n) {
        $vc = $this->_t->virtual[$n];
        if (isset($vc)) {
            if ($vc->source) {
                if (isset($this->__virtmembers[$n]) && $this->__virtmembers[$n] instanceof $vc->class) {
                    return $this->__virtmembers[$n];
                }
                $s = $vc->source;
                $this->__virtmembers[$n] = $this->_db->tables[$vc->class]->find_first($this->$s);
                return $this->__virtmembers[$n];
            } elseif ($vc->collectionsql) {
                # ONE-OR-MANY-CHECK
                # having an expression in collectionsql does not necessarily indicate that a
                # collection gets returned.
                # determine if the primary key in the foreign table is a foreignkey to this model
                # if so, it's a one-to-one mapping, not a collection
                # FIXME might want to move this check into one of the scanning functions and set a flag
                # on the virtual column, and not use collectionsql for this
                $thispk = $this->_t->pk;
                $ft = $this->_db->tables[$vc->class];
                if (is_string($ft->pk)) {
                    $vpk = $ft->pk;
                    $vpkisfk = ($ft->columns[$vpk]->references == get_class($this)) ? true : false;
                    if ($vpkisfk) {

                        if ( !isset($this->__virtmembers[$n]) || !($this->__virtmembers[$n] instanceof $vc->class) ) {
                            $s = $vc->collectionsql;
                            $x = $ft->find_first(array($s, array('id'=>$this->$thispk)));
                            $this->__virtmembers[$n] = $x;
                        }

                    } else {

                        if ( !isset($this->__virtmembers[$n]) || !($this->__virtmembers[$n] instanceof ModelCollection) ) {
                            $s = $vc->collectionsql;
                            $x = $ft->find(array($s, array('id'=>$this->$thispk)));
                            $c = new ModelCollection($vc->class, $this, $this->_db);
                            foreach ($x as $e) {
                                $c[] = $e;
                            }
                            $this->__virtmembers[$n] = $c;
                        }

                    }
                } else {
                    return NULL;
                }
                return $this->__virtmembers[$n];
            }
        }
        return NULL;
    }

    public function __get($n) {
        $m = "__get_$n";
        if (is_callable(array($this, $m))) {
            return call_user_func(array($this, $m), $n);
        }
        if (isset($this->_t->virtual[$n])) {
            return $this->___get_virtual($n);
        }
        return (isset($this->__members[$n]) ? $this->__members[$n] : NULL);
    }

    public function __isset($n) {
        return isset($n);
    }

    public function __unset($n) {
        unset($this->__members[$n]);
    }

    public function dump() {
        $thispk = $this->_t->pk;
        $r[$thispk] = $this->$thispk;
        foreach ($this->__members as $f=>$v) {
            if ($f === $thispk) continue;
            if (is_object($v)) {
                # this case should never actually happen for regular members
                $pk = $this->_db->tables[get_class($v)]->pk;
                $x = sprintf('%s(%s=%d)', get_class($v), $pk, $v->$pk);
            } elseif (is_array($v)) {
                $x = array();
            } else {
                $x = $v;
            }
            $r[$f] = $v;
        }
        foreach (array_keys($this->_t->virtual) as $f) {
            if ($this->_t->virtual[$f]->ignore) continue;
            $v = $this->$f; # virtual column doesn't exist until we request it
            if (is_object($v)) {
                if (! ($v instanceof ModelCollection) ) {
                    $pk = $this->_db->tables[get_class($v)]->pk;
                    $x = sprintf('%s(%s=%d)', get_class($v), $pk, $v->$pk);
                } else {
                    $x = $v->dump();
                }
            } elseif (is_array($v)) {
                $x = $v; # array();
            } else {
                $x = $v;
            }
            $r[$f] = $x;
        }
        return $r;
    }

    public function init($a) {
        foreach ($this->_t->columns as $colname=>$cinfo) {
            $this->$colname = isset($a[$colname]) ? $a[$colname] : NULL;
        }
    }

    public function load() {
        # FIXME
        # assume ->id is set to the primary key
        # then load in the rest of the columns
    }

    public function pre_save() {
        return;
    }

    public function commit() {
        error_log(caller(true)." called ".get_class($this)."->commit(), should use ->save()");
        return $this->save();
    }

    public function save() {
        global $_generation;

        $start_generation = $_generation;
        if (empty($_generation)) {
            $_generation = uniqid();
        }
        if ($_generation === $this->_generation) {
            return;
        }
        $this->_generation = $_generation;
        $this->pre_save();
        if ($this->_is_new_row()) {
            $chgcols = $this->_save_insert();
        } else {
            $chgcols = $this->_save_update();
            $this->_save_virtuals();
        }
        if (!$chgcols) {
            #error_log("no changes in save of ".$this->labelx());
        }
        if ($start_generation != $_generation) {
            $_generation = false;
        }
    }

    private function _save_update() {
        $TB = $this->_t;
        $pk = $TB->pk;

        $c = array();
        $a = array();
        $x = 1;
        foreach ($TB->columns as $colname=>$cinfo) {
            if ($colname === $pk) continue;
            $oldval = @ $this->__original[$colname];
            $newval = @ $this->__members[$colname];
            if ($oldval === $newval) continue;
            $k = $colname[0].($x++);
            $c[] = sprintf('%s = ?:%s', $cinfo->nameQ, $k);
            $a[$k] = @ $this->__members[$colname]; # get raw value
        }

        if (count($c)) {
            $q = "update ".$TB->nameQ." set ".join(', ', $c)." where ".$pk." = ?:id";
            $a['id'] = $this->$pk;

            $sth = $this->_db->dbhandle->prepare($q);
            $sth->execute($a);
            $this->checkpoint();
        }
        return count($c);
    }

    private function _is_new_row() {
        $TB = $this->_t;
        $tn = $TB->nameQ;
        $pk = $TB->pk;
        $pkQ = $TB->columns[$pk]->nameQ;
        $q = "select count(1) from $tn where $pkQ = ?";
        $sth = $this->_db->dbhandle->prepare($q);
        $sth->execute($this->$pk);
        list($c) = $sth->fetchrow_array();
        return !($c);
    }

    protected function _save_virtuals() {
        $TB = $this->_t;
        foreach ($TB->virtual as $f=>$cinfo) {
            # going to recurse infinitely here 
            if (is_object($this->$f)) {
                $this->$f->save();
            }
        }
    }

    private function _save_insert() {
        $TB = $this->_t;
        $pk = $TB->pk;

        $c = array();
        $a = array();
        $x = 1;
        foreach ($TB->columns as $colname=>$cinfo) {
            if ($colname === $pk && !$cinfo->references) {
                # skip if it's the primary key and doesn't reference another table
                continue;
            }
            if (isset($this->__members[$colname])) {
                $k = $colname[0].($x++);
                $a[$colname] = $this->__members[$colname]; # get raw value
            }
        }

        if (count($a)) {
            # since we're inserting this is going to be true
            # unless the table has no columns -- oops
            $tbn = $TB->nameQ;
            $cnames = join(', ', array_keys($a));
            $plcholders = join(', ', array_fill(0, count($a), '?'));
            $q = "insert into $tbn ($cnames) values ($plcholders)";
            $sth = $this->_db->dbhandle->prepare($q);
            $sth->execute_array($a);
            $pkinfo = $TB->columns[$pk];

            # if primary key is a foreign reference, last_insert_id() is undefined
            # since it is not auto-increment, don't try to update our pk value
            # in that case
            if (!$pkinfo->references) {
                $ith = $this->_db->dbhandle->prepare("select last_insert_id()");
                $ith->execute();
                list($id) = $ith->fetchrow_array();
                $this->$pk = $id;
            }
            $this->checkpoint();
        }
        return count($c);
    }

    /*
    was on SchemaColumn
    public get_value_from($o) {
        if ($this->virtual) return NULL;
        if (isset($this->source) && isset($this->table->virtual[$this->source])) {
            $n = $this->source;
            return $o->$n->id;
        } else {
            $n = $this->name;
            return $o->$n;
        }
    }
    */

    # following functions are delegated to the table's find interface
    # god, PHP's object model is awful
    public static function find() {
        $modelclass = self::_find_static_invoking_class();
        $a = func_get_args();
        $o = eval('return '.$modelclass.'::$__table__;');
        return call_user_func_array(array($o, 'find'), $a);
    }

    public static function find_first() {
        $modelclass = self::_find_static_invoking_class();
        $a = func_get_args();
        $code = 'return '.$modelclass.'::$__table__;';
        $o = eval($code);
        return call_user_func_array(array($o, 'find_first'), $a);
    }

    # oh my fucking god
    # compile-time resolution of static calls looses the class the method was invoked on
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
                if ($target === 'parent') {
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

