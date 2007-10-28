<?php

class _object_cache {
    static $cache = array();

    public static function singleton($class, $id, $o) {
        if ($x = _object_cache::get($class, $id)) {
            return $x;
        } else {
            _object_cache::store($class, $id, $o);
            return $o;
        }
    }

    public static function store($class, $pkvalue, $o) {
        lib::el(sprintf('objcache assigning %s(%d)', $class, $pkvalue));
        self::$cache[$class][$pkvalue] = $o;
    }

    public static function get($class, $pkvalue) {
        if ( isset(self::$cache[$class][$pkvalue]) && is_object(self::$cache[$class][$pkvalue]) ) {
            lib::el(sprintf('objcache     using %s(%d)', $class, $pkvalue));
            return self::$cache[$class][$pkvalue];
        }
        #lib::el(sprintf('objcache getfailed %s(%d)', $class, $pkvalue));
        return NULL;
    }

    public static function dump($pre='') {
        print "$pre<pre>";
        print_r(self::$cache);
        print "</pre><hr/>";
        #foreach (self::$cache as $c=>$a) { foreach ($a as $p=>$o) { print "$c = $p\n"; } }
    }
    
    public static function forget($class, $pk=NULL) {
        if (isset($pk)) {
            lib::el("forgetting all cached $class $pk objects");
            unset(self::$cache[$class][$pk]);
        } else {
            lib::el("forgetting all cached $class objects");
            self::$cache[$class] = array();
        }
    }
    
    public static function xflush() {
        lib::el("flushing all cached objects");
        self::$cache = array();
    }
}

class empty_model {  
    public function __call($m, $a) {
        return NULL;
    }
    
    public function __get($n) {
        return NULL;
    }

    public function __set($n, $v) {
        return;
    }
    
    public function __isset($n) {
        return false;
    }

    public function __unset($n) {
        return;
    }
}

class _model_data {
    static public $table = array();
    static public $primary_key = array();
    static public $primary_key_is_foreign = array();

    /*
    create table house (
        id
        family_id
    );

    create table family (
        id
    )

    create table child (
        id
        family_id
    )

    */

    # family::has_one(house) => house.family_id references family.id
    # family is referenced by house
    # family->house is created
    static public $has_one = array();

    # house::belongs_to(family) => house.family_id references family.id
    # house references a family
    # house->family is created
    # child::belongs_to(family) => child.family_id references family.id
    # child references a family
    # child->family is created
    static public $belongs_to = array();

    # family::has_many(child) => child.family_id references family.id
    # family->child is created as an array()
    static public $has_many = array();

    static public $has_collection = array();
}

