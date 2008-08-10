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

class ModelBase {
    protected $_t;
    protected $_db;
}

class ModelCollection extends ModelBase implements Countable, ArrayAccess, Iterator {
    private $_generation;
    private $ownerobj = NULL;
    public $ofclass = NULL;
    private $members = array();
    private $pendingdel = array();
    private $sortby = NULL;
    private $sortsense = NULL;

    public function __construct($ofclass, $owner = NULL, $dbase = NULL) {
        $this->ofclass = $ofclass;
        $this->ownerobj = $owner;
        if ($dbase && $dbase instanceof SchemaDatabase) {
            $this->_t = $dbase->tables[$ofclass];
            $this->_db = $dbase;
        }
    }

    public function save() {
        #error_log("skipping save of collection type ".$this->ofclass." for ".$this->ownerobj->labelx());
        /*
        global $_generation;

        $start_generation = $_generation;
        if (empty($_generation)) {
            $_generation = uniqid();
        }
        if ($_generation === $this->_generation) {
            return;
        }
        error_log("saving collection of type ".$this->ofclass." for ".$this->ownerobj->labelx());
        if ($start_generation != $_generation) {
            $_generation = false;
        }
        */
    }

    public function merge($a) {
        # offsetSet will take care of only adding elements that 
        # are objects that are instances of this collection's ofclass
        foreach ($a as $x) {
            $this[] = $x;
        }
        #d(sprintf("%d elements merged in to make %d total", count($a), count($this)));
    }

    public function walk($callback /* .... */ ) {
        $a = func_get_args();
        array_unshift($a, $this);
        call_user_func_array('walk', $a);
    }

    public function sort($field, $func=null) {
        $this->sortby = $field;
        $this->sortsense = 1;
        if (!isset($func)) {
            $func = array($this, '_sort');
        }
        usort($this->members, $func);
        $this->sortby = NULL;
    }

    public function rsort($field) {
        $this->sortby = $field;
        $this->sortsense = -1;
        usort($this->members, array($this, '_sort'));
        $this->sortby = NULL;
    }

    public function _sort($a, $b) {
        $f = $this->sortby;
        if ($a->$f === $b->$f) {
            return 0;
        }
        if ($this->sortsense == 1) {
            return ($a->$f < $b->$f) ? -1 : 1;
        }
        return ($a->$f > $b->$f) ? -1 : 1;
    }

    public function randomize() {
        usort($this->members, array($this, '_sortrandom'));
    }

    public function _sortrandom() {
        return rand(-1, 1);
    }

    public function dump($deep=0) {
        $r = array('collection-of'=>$this->ofclass);
        $pk = $this->_t->pk;
        foreach ($this as $v) {
            $x = sprintf('%s(%s=%s)', $this->ofclass, $pk, $v->$pk);
            if ($deep) {
                $r[$x] = $v->dump();
            } else {
                $r[$v->$pk] = $x;
            }
        }
        foreach ($this->pendingdel as $v) {
            $r["deleted-".$v->$pk] = sprintf('%s(%s=%s)', $this->ofclass, $pk, $v->$pk);
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
        if (is_object($value) && ($value instanceof $this->ofclass) && ($value instanceof Model)) {
            if (!isset($this->_t)) {
                # note that $value->_t should be "protected" (see definition of ModelBase)
                # but we can still read here even though we are not in the same inheritence chain
                # assume the table of the first object we're adding
                $this->_t = $value->_t;
            }
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

