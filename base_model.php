<?php

class _object_cache {
    static $cache = array();

    public static function store($class, $pk, $o) {
        lib::el(sprintf('objcache assigning %s(%d)', $class, $o->$PK));
        self::$cache[$class][$pk] = $o;
    }

    public static function get($class, $pk) {
        if (isset(self::$cache[$class][$pk])) {
            lib::el(sprintf('objcache     using %s(%d)', $class, $o->$PK));
            return self::$cache[$class][$pk];
        }
        return NULL;
    }

    public static function forget($class, $pk) {
        unset(self::$cache[$class][$pk]);
    }
}

class _model_data {
    static public $table = array();
    static public $primary_key = array();
    static public $has_one = array();
    static public $has_collection = array();
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


class base_model {
    protected $__members = array();
    protected $__original_values = array();

    public function table($t) {
        _model_data::$table[get_class($this)] = $t;
        _model_data::$primary_key[get_class($this)] = 'id';
        _model_data::$has_one[get_class($this)] = array();
        _model_data::$has_collection[get_class($this)] = array();
    }

    public function primary_key($pk) {
        _model_data::$primary_key[get_class($this)] = $pk;
    }

    public function has_one($model_name, $bycol = NULL) {
        if (!isset($bycol)) {
            $bycol = sprintf('%s_id', $model_name);
        }
        _model_data::$has_one[get_class($this)][$model_name] = $bycol;
    }
    
    public function has_collection($model_name, $bycol = NULL) {
        if (!isset($bycol)) {
            $bycol = sprintf('%s_id', $model_name);
        }
        _model_data::$has_collection[get_class($this)][$model_name] = $bycol;
    }

    public function __construct($a=array()) {
        $this->__members = array();
        $this->__original_values = array();
        foreach ($a as $n=>$v) {
            $this->$n = $v;
        }
    }

    public function __set($n, $v) {
        if (!array_key_exists($n, $this->__original_values) 
                && !in_array($n, _model_data::$has_one[get_class($this)])) {
            $this->__original_values[$n] = $v;
        }
        $this->__members[$n] = $v;
    }

    private function __get_one($n) {
        if (!isset($this->__members[$n])) {
            #lib::el("demand loading single $n");
            $PK = _model_data::$primary_key[get_class($this)];
            $fsk = _model_data::$has_one[get_class($this)][$n];
            $this->__members[$n] = model($n)->find_first(array($fsk=>$this->$PK));
        }
    }

    private function __get_collection($n) {
        if (!isset($this->__members[$n])) {
            #lib::el("demand loading collection $n");
            $PK = _model_data::$primary_key[get_class($this)];
            $fsk = _model_data::$has_collection[get_class($this)][$n];
            $this->__members[$n] = model($n)->find(array($fsk=>$this->$PK));
        }
    }

    public function __get($n) {
        if (isset(_model_data::$has_one[get_class($this)][$n])) {
            $this->__get_one($n);
        }
        if (isset(_model_data::$has_collection[get_class($this)][$n])) {
            $this->__get_collection($n);
        }
        return $this->__members[$n];
    }

    public function __isset($n) {
        return isset($this->__members[$n]);
    }

    public function __unset($n) {
        unset($this->__members[$n]);
    }

    public function commit() {
        global $dbh;

        $PK = _model_data::$primary_key[get_class($this)];
        if (isset($this->$PK)) {
            $this->_commit_update();
            /*
            foreach (_model_data::$has_one[get_class($this)] as $mn=>$fsk) {
                $x = $this->__members[$mn];
                lib::qd($x);
                if (is_object($x) && is_a($x, $mn)) {
                    $x->commit();
                }
            }
            */
        } else {
            $this->_commit_insert();
        }
        $this->_refresh();
    }

    private function _commit_update() {
        # FIXME use dbi identifer quoting
        $q = "update "._model_data::$table[get_class($this)]." set ";
        $f = array();
        $s = array();
        foreach ($this->__original_values as $n=>$ov) {
            #lib::el(array(get_class($this), $n, $ov, $this->$n));
            if (isset(_model_data::$has_one[get_class($this)][$n])
             || isset(_model_data::$has_collection[get_class($this)][$n])) {
                # don't recursive update models/sub-objects
                # developer must manually invoke update on those
                next;
            }
            if ( ! ($this->$n === $ov) ) {
                # FIXME use dbi identifer quoting
                $f[] = sprintf('`%s` = ?', $n);
                $s[] = $this->$n;
            }
        }
        if (count($s)) { # else nothing to update, nothing changed
            $q .= join(', ', $f);
            $PK = _model_data::$primary_key[get_class($this)];
            # FIXME use dbi identifer quoting
            $q .= " where $PK = ?";
            $s[] = $this->$PK;

            global $dbh;
            d($q);
            d($s);
            $sth = $dbh->prepare($q);
            $sth->execute_array($s);
            #lib::el($sth->_stmt());
        }
    }

    private function _commit_insert() {
        lib::el("commit_insert for ".get_class($this));
        $f = array();
        $s = array();
        foreach ($this->__original_values as $n=>$c) {
            if (isset(_model_data::$has_one[get_class($this)][$n])
             || isset(_model_data::$has_collection[get_class($this)][$n])) {
                # don't recursive update models/sub-objects
                # developer must manually invoke update on those
                next;
            }
            $f[] = $n;
            $s[] = $this->$n;
        }
        # FIXME use dbi identifer quoting
        $x = join(', ', array_fill(0, count($f), '?'));
        $q = "insert into "._model_data::$table[get_class($this)]
                ." (".join(', ', $f).") values (".$x.")";

        global $dbh;
        d($q);
        d($s);
        $sth = $dbh->prepare($q);
        $sth->execute_array($s);
        lib::el($sth->_stmt());

        $sth = $dbh->prepare('select last_insert_id()');
        $sth->execute();
        list($id) = $sth->fetchrow_array();
        $PK = _model_data::$primary_key[get_class($this)];
        $this->$PK = $id;
    }

    public function _refresh() {
        global $dbh;

        $PK = _model_data::$primary_key[get_class($this)];
        if (isset($this->$PK) && $this->$PK) {
            # FIXME use DBI identifer quoting
            $q = "select * from "._model_data::$table[get_class($this)]." where $PK = ? limit 1";
            $sth = $dbh->prepare($q);
            $sth->execute($this->$PK);
            $this->__original_values = array();
            $this->__members = array();
            $sth->fetchrow_object($this);
        }
    }

    public function find( /* cond, ?where, ?limit*/ ) {
        $args = func_get_args();
        $cond = array_shift($args);
        if (count($args)) {
            $lastarg = array_pop($args);
            if (is_int($lastarg)) {
                $limit = $lastarg;
            }
        }

        $PK = _model_data::$primary_key[get_class($this)];
        if (intval($cond).'' == "$cond") {
            $a = array($cond);
            $where = $PK.' = ?';
        } elseif (is_array($cond)) {
            $a = array();
            $where = array();
            foreach ($cond as $f=>$v) {
                # FIXME use DBI identifer quoting
                $where[] = sprintf('`%s` = ?', $f);
                $a[] = $v;
            }
            $where = join(' and ', $where);
        } elseif (is_string($cond)) {
            if (count($args)) {
                $a = array_shift($args);
                if (!is_array($a)) {
                    throw new Exception("illegal type for placeholder list");
                }
            } else {
                # assume there are no placeholder variables
                $a = array();
            }
            $where = $cond;
        } else {
            throw new Exception("illegal type for conditions to ".get_class($this)."::find");
        }

        global $dbh;
        # FIXME use DBI identifer quoting
        $q = "select * from "._model_data::$table[get_class($this)]." where ".$where;
        if (isset($limit)) {
            $q .= " limit $limit";
        }
        $sth = $dbh->prepare($q);
        $sth->execute_array($a);
        $r = array();
        $class = get_class($this);
        while($o = $sth->fetchrow_object($class)) {
            if ($x = _object_cache::get($class, $o->$PK)) {
                $o = $x;
            } else {
                _object_cache::store($class, $o->$PK, $o);
            }
            $r[] = $o;
        }
        return $r;
    }

    public function find_first($cond, $where=NULL) {
        $x = $this->find($cond, $where, 1);
        if (count($x)) {
            return $x[0];
        }
        return NULL;
    }

}

if ($__x = opendir("./models")) {
    $__y = array();
    while (($__f = readdir($__x)) !== false) {
        if (filetype("./models/$__f") === 'file' && preg_match('/\.php$/', $__f)) {
            array_push($__y, $__f);
        }
    }
    closedir($__x);
    sort($__y);
    $__p = model(false);
    foreach ($__y as $__f) {
        require_once ("./models/$__f");
        $__x = basename($__f, '.php');
        $__z = $__p.strtoupper($__x);
        $GLOBALS[$__z] = eval("return new $__x();");
        if (method_exists($GLOBALS[$__z], 'init')) {
            $GLOBALS[$__z]->init();
        }
    }
    unset($__y);
    unset($__f);
    unset($__x);
    unset($__z);
    unset($__p);
}

# there's a lot of shit in here to work around the fact that
# PHP's meaning of 'self' in side class static methods is
# totally fucking screwed.
function model($n) {
    static $prefix;

    # the prefix is to avoid other code from accidentially 
    # overwriting these values
    if (!isset($prefix)) $prefix = 'M_'.uniqid();
    if ($n === false) return $prefix;
    $x = $prefix.strtoupper($n);
    if (isset($GLOBALS[$x]) && is_object($GLOBALS[$x])) {
        return $GLOBALS[$x];
    }
    throw new Exception("model $n is not available as $x");
}

