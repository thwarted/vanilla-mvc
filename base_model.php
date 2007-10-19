<?php

class base_model_data {
    static public $___table = array();
}

class base_model {
    protected $__original_values = array();

    public function table($t) {
        base_model_data::$___table[get_class($this)] = $t;
    }

    public function __construct($a=array()) {
        $this->__original_values = array();
        foreach ($a as $n=>$v) {
            $this->$n = $v;
        }
    }

    public function __set($n, $v) {
        if (!isset($this->__original_values[$n])) {
            $this->__original_values[$n] = $v;
        }
        $this->$n = $v;
    }

    public function commit() {
        global $dbh;

        if (isset($this->id)) {
            $this->_commit_update();
        } else {
            $this->_commit_insert();
        }
        $this->_refresh();
    }

    private function _commit_update() {
        # FIXME use dbi identifer quoting
        $q = "update ".base_model_data::$___table[get_class($this)]." set ";
        $f = array();
        $s = array();
        foreach ($this->__original_values as $n=>$ov) {
            if ( ! ($this->$n === $ov) ) {
                # FIXME use dbi identifer quoting
                $f[] = sprintf('`%s` = ?', $n);
                $s[] = $this->$n;
            }
        }
        $q .= join(', ', $f);
        $q .= " where id = ?";
        $s[] = $this->id;

        global $dbh;
        d($q);
        d($s);
        $sth = $dbh->prepare($q);
        $sth->execute_array($s);
    }

    private function _commit_insert() {
        lib::el("commit_insert for ".get_class($this));
        $f = array();
        $s = array();
        foreach ($this->__original_values as $n=>$c) {
            $f[] = $n;
            $s[] = $this->$n;
        }
        # FIXME use dbi identifer quoting
        $x = join(', ', array_fill(0, count($f), '?'));
        $q = "insert into ".base_model_data::$___table[get_class($this)]
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
        $this->id = $id;
    }

    public function _refresh() {
        global $dbh;

        if (isset($this->id) && !$this->id) {
            # FIXME use DBI identifer quoting
            $q = "select * from ".base_model_data::$___table[get_class($this)]." where id = ? limit 1";
            $sth = $dbh->prepare($q);
            $sth->execute($this->id);
            $this->__original_values = array();
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

        if (intval($cond).'' == "$cond") {
            $a = array($cond);
            $where = 'id = ?';
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
        $q = "select * from ".base_model_data::$___table[get_class($this)]." where ".$where;
        if (isset($limit)) {
            $q .= " limit $limit";
        }
        $sth = $dbh->prepare($q);
        $sth->execute_array($a);
        d($sth->_stmt());
        $r = array();
        while($o = $sth->fetchrow_object(get_class($this))) {
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

