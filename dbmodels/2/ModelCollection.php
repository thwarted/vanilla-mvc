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

# requires walk from ModelCollection.php

class ModelCollection implements Countable, ArrayAccess, Iterator {
    private $_ofclass = NULL;
    private $_ownerobj = NULL;
    private $_members = array();
    private $_sortby = NULL;
    private $_sortsense = NULL;

    public function __construct($ofclass, $owner = NULL) {
        $this->_ofclass = $ofclass;
        $this->_ownerobj = $owner;
    }

    public function merge($a) {
        # offsetSet will take care of only adding elements that 
        # are objects that are instances of this collection's ofclass
        foreach ($a as $x) {
            $this[] = $x;
        }
        d(sprintf("%d elements merged in to make %d total", count($a), count($this)));
    }

    public function walk($callback /* .... */ ) {
        $a = func_get_args();
        array_unshift($a, $this);
        call_user_func_array('walk', $a);
    }

    public function sort($field, $func=null) {
        $this->_sortby = $field;
        $this->_sortsense = 1;
        if (!isset($func)) {
            $func = array($this, '_sort');
        }
        usort($this->_members, $func);
        $this->_sortby = NULL;
    }

    public function rsort($field) {
        $this->_sortby = $field;
        $this->_sortsense = -1;
        usort($this->_members, array($this, '_sort'));
        $this->_sortby = NULL;
    }

    public function _sort($a, $b) {
        $f = $this->_sortby;
        if ($a->$f === $b->$f) {
            return 0;
        }
        if ($this->_sortsense == 1) {
            return ($a->$f < $b->$f) ? -1 : 1;
        }
        return ($a->$f > $b->$f) ? -1 : 1;
    }

    public function randomize() {
        usort($this->_members, array($this, '_sortrandom'));
    }

    public function _sortrandom() {
        return rand(-1, 1);
    }

    public function dump($deep=0) {
        $r = array('collection-of'=>$this->_ofclass);
        $ti = Model::_modelinfo($this->_ofclass);
        foreach ($this as $v) {
            $keys = array();
            foreach ($ti['primarykey'] as $pk) {
                $keys[] = sprintf('%s=%s', $pk, $v->$pk);
            }
            $x = sprintf('%s(%s)', $this->_ofclass, join(',', $keys));
            if ($deep) {
                $r[$x] = $v->dump();
            } else {
                $r[$v->$pk] = $x;
            }
        }
        #foreach ($this->pendingdel as $v) {
        #    $r["deleted-".$v->$pk] = sprintf('%s(%s=%s)', $this->_ofclass, $pk, $v->$pk);
        #}
        return $r;
    }

    /*
    public function singular_ids() {
        $r = array();
        $pk = $this->_t->pk;
        foreach ($this as $v) {
            if (is_object($v) && $v instanceof $this->_ofclass) {
                $r[] = $v->$pk;
            }
        }
        return $r;
    }
    */

    # Countable interface
    public function count() { return count($this->_members); }

    # ArrayAccess interface
    public function offsetExists($offset) {
        return (isset($this->_members[$offset]) && is_object($this->_members[$offset]) && ($this->_members[$offset] instanceof $this->_ofclass));
    }

    public function offsetGet($offset) {
        if ($this->_offsetExists($offset)) { return $this->_members[$offset]; }
        # sanity check, clean up the collection if it's not the correct type
        unset($this->_members[$offset]);
        return NULL;
    }

    public function offsetSet($offset, $value) {
        if (is_object($value) && ($value instanceof $this->_ofclass) && ($value instanceof Model)) {
            if (empty($offset)) {
                $offset = $value->pkidentifier();
                #error_log("offset was empty for ".$this->_ofclass.", setting to $offset"); 
            }
            $this->_members[$offset] = $value;
        }
    }

    public function offsetUnset($offset) {
        $o = $this->_members[$offset];
        #if (isset($o) && is_object($o) && $o instanceof $this->_ofclass) {
        #    $this->pendingdel[$offset] = $o;
        #}
        unset($this->_members[$offset]);
    }
    public function current() { return current($this->_members); }
    public function rewind() { return reset($this->_members); }
    public function key() { return key($this->_members); }
    public function next() { return next($this->_members); }
    public function valid() { $x = current($this->_members); return (is_object($x) && ($x instanceof $this->_ofclass)) ? true : false; }

}

