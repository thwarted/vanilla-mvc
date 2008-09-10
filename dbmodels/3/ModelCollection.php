<?php

class ModelCollection implements Countable, ArrayAccess, Iterator {
    private $members = array();
    public $ofclass = NULL;
    private $owner = NULL;

    public function __construct($ofclass, $owner = NULL) {
        $this->ofclass = $ofclass;
        $this->owner = $owner;
    }

    public function dump($deep=false) { # $deep is unimplemented
        $r = array('collection-of'=>$this->ofclass);
        $complexkey = Model::_has_complex_primary_key($this->ofclass);
        foreach ($this as $v) {
            $x = $v->pp();
            if ($complexkey) {
                # no really good thing to do here
                $r[] = $x;
            } else {
                $k = $v->_simple_primary_key();
                $r[$v->$k] = $x;
            }
        }
        return $r;
    }

    public function merge($array) {
        foreach ($array as $a) {
            $this[] = $a;
        }
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

    # Countable interface
    public function count() { return count($this->members); }

    # ArrayAccess interface
    public function offsetExists($offset) {
        return (isset($this->members[$offset]) && is_object($this->members[$offset]) && ($this->members[$offset] instanceof $this->ofclass));         
    }

    public function offsetGet($offset) {
        if ($this->offsetExists($offset)) {
             return $this->members[$offset];
        }
        # sanity check, remove items that are not of the correct class
        unset($this->members[$offset]);
        return NULL;
    }

    public function offsetSet($offset, $value) {
        if (is_object($value) && ($value instanceof $this->ofclass) && ($value instanceof Model)) {
            # consider explictly setting the offset to the primary key of the object being set
            # original implementation does that
            # would make the collection into a set, since the same row could not be added multiple times
            if ($offset) {
                $this->members[$offset] = $value;
            } else {
                $this->members[] = $value;
            }
        }
    }

    public function offsetUnset($offset) {
        if (isset($offset)) {
            unset($this->members[$offset]);
        }
    }

    public function current() { return current($this->members); }
    public function rewind() { return reset($this->members); }
    public function key() { return key($this->members); }
    public function next() { return next($this->members); }
    public function valid() { $x = current($this->members); return (is_object($x) && ($x instanceof $this->ofclass)) ? true : false; }

}

