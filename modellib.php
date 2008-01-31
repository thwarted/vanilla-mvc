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

class _object_cache {
    static $cache = array();

    public static function singleton($o, $id=NULL, $class=NULL) {
        if (!is_object($o) || !($o instanceof Model)) {
            throw new Exception("trying to get singleton of non-Model instance");
        }
        if (!isset($class)) {
            $class = get_class($o);
        }
        if (!isset($id)) {
            $PK = eval('$x = '.$class.'::$__table__; return $x->pk;');
            $id = $o->$PK;
        }
        if ($x = _object_cache::get($class, $id)) {
            return $x;
        } else {
            _object_cache::store($class, $id, $o);
            return $o;
        }
    }

    public static function store($class, $pkvalue, $o) {
        #lib::el(sprintf('objcache assigning %s(%d)', $class, $pkvalue));
        self::$cache[$class][$pkvalue] = $o;
    }

    public static function get($class, $pkvalue) {
        if ( isset(self::$cache[$class][$pkvalue]) && is_object(self::$cache[$class][$pkvalue]) ) {
            #lib::el(sprintf('objcache     using %s(%d)', $class, $pkvalue));
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
            #lib::el("forgetting all cached $class $pk objects");
            unset(self::$cache[$class][$pk]);
        } else {
            #lib::el("forgetting all cached $class objects");
            self::$cache[$class] = array();
        }
    }
    
    public static function xflush() {
        #lib::el("flushing all cached objects");
        self::$cache = array();
    }
}

class empty_model extends EmptyModel { }

class EmptyModel {
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

