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

class ModelCollection implements Countable, ArrayAccess, Iterator {
    private $_generation;
    private $_t;
    private $_db;
    private $ownerobj = NULL;
    private $ofclass = NULL;
    private $members = array();
    private $pendingdel = array();

    public function __construct($ofclass, $owner, $dbase) {
        $this->ofclass = $ofclass;
        $this->ownerobj = $owner;
        $this->_t = $dbase->tables[$ofclass];
        $this->_db = $dbase;
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
        error_log("saving collection of type ".$this->ofclass);
        if ($start_generation != $_generation) {
            $_generation = false;
        }
    }

    public function dump() {
        $r = array('collection-of'=>$this->ofclass);
        $pk = $this->_t->pk;
        foreach ($this as $v) {
            $x = sprintf('%s(%s=%d)', $this->ofclass, $pk, $v->$pk);
            $r[$v->$pk] = $x;
        }
        foreach ($this->pendingdel as $v) {
            $r["deleted-".$v->$pk] = sprintf('%s(%s=%d)', $this->ofclass, $pk, $v->$pk);
        }
        return $r;
    }

    public function singular_ids() {
        $r = array();
        $pk = $this->_t->pk;
        foreach ($this as $v) {
            if (is_object($v) && $v instanceof $this->ofclass) {
                $r[] = $v->$pk;
            }
        }
        return $r;
    }

    # Countable interface
    public function count() { return count($this->members); }

    # ArrayAccess interface
    public function offsetExists($offset) {
        return (isset($this->members[$offset]) && is_object($this->members[$offset]) && ($this->members[$offset] instanceof $this->ofclass));
    }

    public function offsetGet($offset) {
        if ($this->offsetExists($offset)) { return $this->members[$offset]; }
        # sanity check, clean up the collection if it's not the correct type
        unset($this->members[$offset]);
        return NULL;
    }

    public function offsetSet($offset, $value) {
        if (is_object($value) && ($value instanceof $this->ofclass)) {
            if (empty($offset)) {
                $pk = $this->_t->pk;
                $offset = $value->$pk;
                #error_log("offset is empty for ".$this->ofclass.".$pk, setting to $offset"); 
            }
            $this->members[$offset] = $value;
        }
    }
    public function offsetUnset($offset) {
        $o = $this->members[$offset];
        if (isset($o) && is_object($o) && $o instanceof $this->ofclass) {
            $this->pendingdel[$offset] = $o;
        }
        unset($this->members[$offset]);
    }
    public function current() { return current($this->members); }
    public function rewind() { return reset($this->members); }
    public function key() { return key($this->members); }
    public function next() { return next($this->members); }
    public function valid() { $x = current($this->members); return (is_object($x) && ($x instanceof $this->ofclass)) ? true : false; }

}

