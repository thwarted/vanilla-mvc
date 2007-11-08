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

class Model {
    protected $__db;
    protected $__members = array();
    protected $__virtmembers = array();
    protected $__original = array();
    protected $__jointype = NULL;

    public function _from_database($x) {
        $this->__db = $x;
    }

    public function ___set_virtual($n, $v, $TB) {
        # get the primary key for the value we are setting
        $PK = $this->__db->tables[$TB->virtual[$n]->class]->pk;
        # find out which REAL/non-virtual column we should update with the key value
        $source = $TB->virtual[$n]->source;
        if (isset($v) && $v instanceof $TB->virtual[$n]->class) {
            $this->$source = $v->$PK;
        } else {
            $this->$source = NULL;
        }
    }

    public function __set($n, $v) {
        $c = get_class($this);
        $TB = @ $this->__db->tables[$c];

        if (isset($TB->virtual[$n])) {
            return $this->___set_virtual($n, $v, $TB);
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
        $c = get_class($this);
        $vc = @ $this->__db->tables[$c]->virtual[$n];
        if (isset($vc)) {
            if ($vc->source) {
                if (isset($this->__virtmembers[$n]) && $this->__virtmembers[$n] instanceof $vc->class) {
                    return $this->__virtmembers[$n];
                }
                $s = $vc->source;
                $this->__virtmembers[$n] = $this->__db->tables[$vc->class]->find_first($this->$s);
                return $this->__virtmembers[$n];
            } elseif ($vc->collectionsql) {
                # having an expression in collectionsql does not necessarily indicate that a
                # collection gets returned.
                # determine if the primary key in the foreign table is a foreignkey to this model
                # if so, it's a one-to-one mapping, not a collection
                # FIXME might want to move this check into one of the scanning functions and set a flag
                # on the virtual column, and not use collectionsql for this
                $ft = $this->__db->tables[$vc->class];
                $vpk = $ft->pk;
                $vpkisfk = ($ft->columns[$vpk]->references == $c) ? true : false;
                if ($vpkisfk) {

                    if ( !isset($this->__virtmembers[$n]) || !($this->__virtmembers[$n] instanceof $vc->class) ) {
                        $s = $vc->collectionsql;
                        $x = $ft->find_first(array($s, array('id'=>$this->id)));
                        $this->__virtmembers[$n] = $x;
                    }

                } else {

                    if ( !isset($this->__virtmembers[$n]) || !($this->__virtmembers[$n] instanceof ModelCollection) ) {
                        $s = $vc->collectionsql;
                        $x = $ft->find(array($s, array('id'=>$this->id)));
                        $c = new ModelCollection($vc->class, $this, $this->__db);
                        foreach ($x as $e) {
                            $c[] = $e;
                        }
                        $this->__virtmembers[$n] = $c;
                    }

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
        $c = get_class($this);
        if (isset($this->__db->tables[$c]->virtual[$n])) {
            return $this->___get_virtual($n);
        }
        return $this->__members[$n];
    }

    public function __isset($n) {
        return isset($n);
    }

    public function __unset($n) {
        unset($this->__members[$n]);
    }

    public function dump() {
        foreach ($this->__members as $f=>$v) {
            if (is_object($v)) {
                $pk = $this->__db->tables[get_class($v)]->pk;
                $x = sprintf('%s(%s=%d)', get_class($v), $pk, $v->$pk);
            } elseif (is_array($v)) {
                $x = array();
            } else {
                $x = $v;
            }
            $r[$f] = $v;
        }
        foreach (array_keys($this->__db->tables[get_class($this)]->virtual) as $f) {
            if ($this->__db->tables[get_class($this)]->virtual[$f]->ignore) continue;
            $v = $this->$f; # virtual column doesn't exist until we request it
            if (is_object($v)) {
                if (! ($v instanceof ModelCollection) ) {
                    $pk = $this->__db->tables[get_class($v)]->pk;
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
        foreach ($this->__db->tables[get_class($this)]->columns as $colname=>$cinfo) {
            $this->$colname = isset($a[$colname]) ? $a[$colname] : NULL;
        }
    }

    public function load() {
        global $dbh;
        # FIXME
        # assume ->id is set to the primary key
        # then load in the rest of the columns
    }

    public function save() {
        $pk = $this->__db->tables[get_class($this)]->pk;
        if (isset($this->$pk)) {
            $this->_save_update();
            $this->_save_virtuals();
        } else {
            $this->_save_insert();
        }
    }
    private function _save_update() {
        global $dbh;

        $TB = $this->__db->tables[get_class($this)];
        $pk = $TB->pk;

        $c = array();
        $a = array();
        $x = 1;
        foreach ($TB->columns as $colname=>$cinfo) {
            if ($colname === $pk) continue;
            if ($this->__members[$colname] === $this->__original[$colname]) continue;
            $k = $colname[0].($x++);
            $c[] = sprintf('%s = ?:%s', $cinfo->nameQ, $k);
            $a[$k] = $this->__members[$colname]; # get raw value
        }

        if (count($c)) {
            $q = "update ".$TB->nameQ." set ".join(', ', $c)." where ".$pk." = ?:id";
            $a['id'] = $this->$pk;

            print "$q\n";
            print_r($a);

            $sth = $dbh->prepare($q);
            $sth->execute($a);
            $this->checkpoint();
        }
    }

    private function _save_virtuals() {
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

}

