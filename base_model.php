<?php

require_once "vanilla/modellib.php";

class base_model {
    protected $__members = array();
    protected $__original_values = array();

    public function table($t) {
        _model_data::$table[get_class($this)] = $t;
        _model_data::$primary_key[get_class($this)] = 'id';
        _model_data::$primary_key_is_foreign[get_class($this)] = false;
        _model_data::$has_one[get_class($this)] = array();
    }

    public function primary_key($pk, $isforeign=false) {
        _model_data::$primary_key[get_class($this)] = $pk;
        _model_data::$primary_key_is_foreign[get_class($this)] = $isforeign;
    }

    public function has_one($model_name, $bycol = NULL) {
        # family::has_one(house) => house.family_id references family.id
        # family->house is created

        # model_name.bycol points to this
        # accessible via this->model_name
        # this->model_name->bycol should point to this
        if (!isset($bycol)) {
            $bycol = sprintf('%s_id', get_class($this));
        }
        _model_data::$has_one[get_class($this)][$model_name] = $bycol;
    }
    
    public function belongs_to($model_name, $bycol = NULL, $thisfieldname=NULL) {
        # house::belongs_to(family) => house.family_id references family.id
        # house->family is created

        # this.bycol points to model_name (the inverse relationship of has_one and has_many)
        # accessible via this->model_name
        # this->bycol is updatable
        if (!isset($bycol)) {
            $bycol = sprintf('%s_id', $model_name);
        }
        if (!isset($thisfieldname)) {
            $thisfieldname = preg_replace('/_id$/', '', $bycol);
        }
        _model_data::$belongs_to[get_class($this)][$thisfieldname] = array($model_name, $bycol);
    }

    public function has_many($model_name, $bycol=NULL) {
        # family::has_many(child) => child.family_id references family.id
        # family->child = array(of child)

        # more than one model_name.bycol points to this
        # accessible via this->model_name as an array
        # this->model_name[...]->bycol should point to this
        if (!isset($bycol)) {
            $bycol = sprintf('%s_id', $model_name);
        }
        _model_data::$has_many[get_class($this)][$model_name] = $bycol;
    }

    public function __construct($a=array()) {
        $this->__members = array();
        $this->__original_values = array();
        foreach ($a as $n=>$v) {
            $this->$n = $v;
        }
    }

    public function __set($n, $v) {
        $m = "__set_$n";
        if (is_callable(array($this, $m))) {
            return $this->$m($v);
        }
        if (!array_key_exists($n, $this->__original_values) 
                && !in_array($n, _model_data::$has_one[get_class($this)])) {
            $this->__original_values[$n] = $v;
        }
        $this->__members[$n] = $v;
    }

    private function ___get_one($n) {
        if (!isset($this->__members[$n])) {
            #lib::el("demand loading one $n for ".get_class($this));
            $PK = _model_data::$primary_key[get_class($this)];
            $me = $this->$PK;

            $fk = _model_data::$has_one[get_class($this)][$n];
            $this->__members[$n] = model($n)->find_first( array($fk=>$me) );
        }
        return $this->__members[$n];
    }

    private function ___get_belongs_to($n) {
        if (!isset($this->__members[$n])) {
            #lib::el("demand loading belongs to $n for ".get_class($this));
            list($model_name, $fsk) = _model_data::$belongs_to[get_class($this)][$n];
            if (isset($this->$fsk)) {
                $this->__members[$n] = model($model_name)->find_first($this->$fsk);
            }
        }
        return $this->__members[$n];
    }

    private function ___get_many($n) {
        if (!isset($this->__members[$n])) {
            #lib::el("demand loading many $n for ".get_class($this));
            $PK = _model_data::$primary_key[get_class($this)];
            $me = $this->$PK;

            $fk = _model_data::$has_many[get_class($this)][$n];
            $this->__members[$n] = model($n)->find( array($fk=>$me) );
        }
        return $this->__members[$n];
    }

    public function __get($n) {
        $m = "__get_$n";
        if (is_callable(array($this, $m))) {
            return $this->$m($n);
        }
        if (isset(_model_data::$has_one[get_class($this)][$n])) {
            return $this->___get_one($n);
        }
        if (isset(_model_data::$belongs_to[get_class($this)][$n])) {
            return $this->___get_belongs_to($n);
        }
        if (isset(_model_data::$has_many[get_class($this)][$n])) {
            return $this->___get_many($n);
        }
        return isset($this->__members[$n]) ? $this->__members[$n] : NULL;
    }

    public function __isset($n) {
        $m = "__isset_$n";
        if (is_callable(array($this, $m))) {
            return $this->$m($n);
        }
        return isset($this->__members[$n]);
    }

    public function __unset($n) {
        $m = "__unset_$n";
        if (is_callable(array($this, $m))) {
            return $this->$m($n);
        }
        unset($this->__members[$n]);
    }

    public function dump() {
        # for debugging
        # use in templates like:
        #   {$m->dump()|@d} (preferred)
        # or
        #   <pre>{$m->dump()|@printr}</pre>
        $r = array();
        foreach ($this->__members as $f=>$v) {
            if (is_object($v)) {
                $PK = _model_data::$primary_key[get_class($v)];
                $x = sprintf('%s(%d)', get_class($v), $v->$PK);
            } elseif (is_array($v)) {
                $x = array();
            } else {
                $x = $v;
            }
            $r[$f] = $x;
        }
        return $r;
    }

    public function commit() {
        global $dbh;

        $PK = _model_data::$primary_key[get_class($this)];
        # FIXME review this
        # the primary key may be a foreign key in a one-to-one mapping
        # which means we set the primary key to the remote value
        # do an "insert or replace" or "insert on duplicate key update.."
        # then
        if (isset($this->$PK) && !_model_data::$primary_key_is_foreign[get_class($this)]) {
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
             || isset(_model_data::$has_many[get_class($this)][$n])) {
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
             || isset(_model_data::$has_many[get_class($this)][$n])) {
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
            # FIXME iterate over has_one and has_many and force updates on them too
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

        if (empty($cond)) {
            return array();
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
            /*
            if ($x = _object_cache::get($class, $o->$PK)) {
                $o = $x;
            } else {
                _object_cache::store($class, $o->$PK, $o);
            }
            */
            $r[] = _object_cache::singleton($class, $o->$PK, $o);
        }
        return $r;
    }

    public function find_first($cond, $where=NULL) {
        $x = $this->find($cond, $where, 1);
        if (count($x)) {
            # return first element independent of the key
            # without modifying the array (like array_shift would)
            reset($x);
            list($k, $v) = each($x);
            return $v;
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
    # FIXME this could be done on-demand and by using a static
    # rather than globals
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

