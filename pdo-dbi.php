<?php

# simple wrapper class to make PHP's PDO be somewhat more Perl DBI-ish
# and increase database backend portability
#
# sample connection strings
#     $dbh = new PDODBI('odbc:NAME', 'user', 'pass');
#     $dbh = new PDODBI('mysql:host=x;dbname=y', 'user', 'pass');
#     $dbh = new PDODBI('sqlite:/opt/databases/mydb.sq3');
#     $dbh = new PDODBI('sqlite::memory:');
#     $dbh = new PDODBI('sqlite2:/opt/databases/mydb.sq2');
#     $dbh = new PDODBI('sqlite2::memory:');
#
# error handling could use some review/improvement


class PDODBI extends PDO {
    public function prepare($s, $do=array()) {
        #$x = $this->getAttribute(PDO::ATTR_DRIVER_NAME);
        #if ($x === 'mysql') { $do[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true; }

        #$s = preg_replace("/\n+/", '', $s);
        #print "<pre>"; print_r($s); print "</pre>";
        $x = parent::prepare($s, $do);
        if (!$x) {
            #$tb = debug_backtrace();
            #$where = sprintf('%s:%d', $tb[0]['file'], $tb[0]['line']);
            $ex = join("\n", $this->errorInfo());
            throw new Exception($ex);
        }
        $x = & new PDODBIstmt($x);
        return $x;
    }

    public function insert_id() {
        return $this->lastInsertId();
    }
}

class PDODBIstmt {
    private $sth;

    public function __construct($stmthandle) {
        $this->sth = $stmthandle;
    }

    public function __destruct() {
        $this->finish();
    }

    public function __call($n, $a) {
        $callback = array($this->sth, $n);
        return call_user_func_array($callback, $a);
    }

    public function execute() {
        $a = func_get_args();
        return $this->sth->execute($a);
    }

    public function execute_array($a) {
        return $this->sth->execute($a);
    }

    public function fetchrow_arrayref() {
        return $this->fetchrow_array();
    }

    public function fetchrow_array() {
        return $this->sth->fetch(PDO::FETCH_NUM);
    }

    public function fetchrow_hashref() {
        return $this->fetchrow_hash();
    }

    # some drivers (mysql) return column names with the case specified
    # other drivers (interbase/firebird) return column names forcefully capitalized
    # thus, making string hash indexes non-portable, so force everything to lowercase
    public function fetchrow_hash() {
        $r = $this->sth->fetch(PDO::FETCH_ASSOC);
        if (!$r) { return $r; }
        $n = array();
        foreach ($r as $k=>$v) {
            $n[strtolower($k)] = $v;
        }
        return $n;
    }

    # same as above
    public function fetchrow_object() {
        $r = $this->sth->fetch(PDO::FETCH_OBJ);
        if (!$r) { return $r; }
        $c = get_class($r);
        $n = & new $c();
        foreach (get_object_vars($r) as $k=>$v) {
            $k = strtolower($k);
            @ $n->$k = $v;
        }
        return $n;
    }

    public function affected_rows() {
        return $this->sth->rowCount();
    }

    public function num_rows() {
        return NULL;
    }

    public function finish() {
        $this->sth->closeCursor();
        # do nothing, nothing to be done; compatiblity only
    }

    public function _stmt() {
        return $this->sth->queryString;
    }

}

?>
