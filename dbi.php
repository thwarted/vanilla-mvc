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

# this should really be abstracted better, so that DBIstatement can use PDO
# to do all the parameters in the queries

# TODO
# for those drivers that use PDO, catch PDOException and map to DBIException

/*
 * CONNECTING
 *  $dbh = DBI::connect('dbi:DatabaseType:data=base;specific=options');
 *       dbi:sqlite:dbname=/path
 *       dbi:mysql:host=x;user=x;pass=x;dbname=x;port=x;persistent={1,0}
 *         (there is no way to specify a semicolon in a parameter value)
 *
 * SIMPLE POSITIONAL SYNTAX
 *  select col from table where x = ? and y = ?
 *   ->execute($x, $y)
 *   $l = array($x, $y);
 *   ->execute_array($l);
 *
 * SIMPLE NAMED SYNTAX
 *  select col from table where x = ?:xval and y = ?:yval
 *   ->execute(array('xval'=>$x, 'yval'=>$y));
 *
 * NAMED SYNTAX WITH ARRAY JOIN
 *  select col from table where x in (?:xval:join) and y = ?:yval
 *   $x = array('one', 'two', 3);
 *   ->execute(array('xval:join'=>$x, 'yval'=>10))
 *     = select col from table where x in ('one', 'two', 3) and y = 10
 *
 */

require_once "vanilla/dbcond.php";

class DBIException extends Exception {
    private $stmt;
    public function setStatement($s) {
        $this->stmt = $s;
    }
    public function getStatement() {
        return $this->stmt;
    }
}

class DBIstatement {
    private $dbh;
    private $sql;
    private $stmttype;
    private $sections;
    private $bindings;
    private $bindtypes;
    private $cursor_handle;
    private $executed_stmt;
    private $execution_time;

    public function __construct(&$dbh, $statement) {
        $statement = trim($statement);
        if (preg_match('/^\W*(\w+)/', $statement, $m)) {
            list($all, $type) = $m;
            $this->stmttype = strtolower($type);
        } else {
            $this->stmttype = 'unknown';
        }
        $this->dbh = $dbh;
        $this->sql = $statement;
        if (preg_match('/\?:\w+/', $statement)) {
            $this->bindtypes = 'labels';
        } else {
            $this->bindtypes = 'positional';
            $this->sections = preg_split('/\?/', $statement);
        }

        if ($this->bindtypes == 'positional') {
            $c = count($this->sections);
            if ($c > 2) {
                $this->bindings = array_fill(0, $c - 1, 0);
            } else {
                $this->bindings = array();
            }
        } else {
            $this->bindings = array();
        }
    }

    public function __destruct() {
        $this->finish();
    }

    public function bind_param($index, $value, $type=NULL) {
        /* $type is currently unused */
        $index = intval($index);
        if ($index > 0 && $index < count($this->bindings)) {
            $this->bindings[$index] = $value;
        } else {
            /* no error is raised, but perhaps should be */
        }
    }

    public function execute() {

        $numargs = func_num_args();
        if ($numargs) {
            $this->bindings = func_get_args();
        }
        $stmt = '';
        $success = true;
        if ($this->bindtypes === "positional") {
            if (count($this->sections) > 1) {
                foreach ($this->sections as $s) {
                    $stmt .= $s;
                    if (count($this->bindings) < 1) {
                        $this->success = false;
                        break;
                    }
                    $v = array_shift($this->bindings);
                    $stmt .= $this->dbh->quote($v);
                }
            } else {
                $stmt = $this->sections[0];
            }
        } else {
            $stmt = $this->sql;
            $this->bindings = array_shift($this->bindings);
            if (!is_array($this->bindings)) {
                /* named binding parameters used, but didn't pass an array */
                throw new DBIException('named binding parameters used, but did not pass an array');
            }

            $search1 = array();
            $replace1 = array();
            foreach ($this->bindings as $k=>$v) {
                $search1[] = "?:$k";
                $v = $this->dbh->quote($v);
                if (preg_match('/^\w+:join$/', $k)) {
                    if (is_array($v)) {
                        $v = join(',', $v);
                    } else {
                        throw new DBIException(":join named parameter ($k) specified for non-array value");
                    }
                }
                if (is_array($v)) {
                    throw new DBIException("query value is an array");
                }
                $replace1[] = $v;
            }
            /* slight chance that the ?:\w+ string could appear in a quoted string */
            /* if there is a binding they didn't specify, let the SQL parser detect it */
            $stmt = str_replace($search1, $replace1, $stmt);
        }
        if (!$stmt) {
            /* no statement resulted? */
            throw new DBIException('empty statement, this should not happen');
        }
        if (!$success) {
            /* too few binding parameters */
            throw new DBIException("too few binding parameters in query $stmt");
        }
        $this->bindings = array();
        $qstart = DBI::getmicrotime();
        $this->cursor_handle = $this->dbh->dbd->query($stmt);
        $qend = DBI::getmicrotime();
        $this->executed_stmt = $stmt;
        if (preg_match('/^\s*(\w+)\b/', $stmt, $m)) {
            @ DBI::$statement_types[strtolower($m[1])]++;
        }
        $qlen = sprintf('%0.5f', $qend - $qstart);
        if (!empty($_SERVER['debugsql'])) d($stmt, 'execution time: ' . $qlen . ' sec');
        #error_log($stmt);
        $this->execution_time = $qlen;
        DBI::$query_runtime += $qlen;

        if (!$this->cursor_handle) {
            $e = new DBIException($this->dbh->dbd->error());
            $e->setStatement($stmt);
            throw $e;
        }
        return $this->cursor_handle;
    }

    /*
     * like perl's DBI::execute_array, execute the statement for each array
     * element passed
     * ->execute_array(array(stmt-params), array(stmt-params) ... )
     */
    public function execute_array() {
        $g = func_get_args();
        foreach ($g as $t) {
            if (is_array($t)) {
                call_user_func_array(array(&$this, 'execute'), $t);
            } else {
                /* a non-array was passed? we skip it */
                /* no error is raised, but perhaps should be */
            }
        }
    }

    public function num_rows() {
        return $this->dbh->dbd->num_rows($this->cursor_handle);
    }

    public function affected_rows() {
        return $this->dbh->dbd->affected_rows($this->cursor_handle);
    }

    public function insert_id() {
        return $this->dbh->dbd->insert_id();
    }

    public function finish() {
        if ($this->cursor_handle) {
            $this->dbh->dbd->free_result($this->cursor_handle);
        }
        /* we want to be able to ->finish statements that don't have cursor_handles, */
        /* so ignore it not being valid */
    }

    public function fetchrow_array() {

        if ($this->cursor_handle) {
            DBI::$fetchrow_count++;
            return $this->dbh->dbd->fetch_row($this->cursor_handle);
        }
        /* should raise error about trying to read from an invalid cursor_handle */
    }

    public function fetchrow_arrayref() {
        return $this->fetchrow_array();
    }

    public function fetchrow_hash() {
        if ($this->cursor_handle) {
            DBI::$fetchrow_count++;
            return $this->dbh->dbd->fetch_hash($this->cursor_handle);
        }
        /* should raise error about trying to read from an invalid cursor_handle */
    }

    public function fetchrow_hashref() {
        DBI::$fetchrow_count++;
        return $this->fetchrow_hash();
    }

    public function fetchrow_object($baseclass = NULL) {
        if (isset($baseclass)) {
            if (is_object($baseclass)) {
                $r = $baseclass;
            } elseif (is_string($baseclass) && class_exists($baseclass)) {
                $r = new $baseclass();
            } else {
                return $this->dbh->dbd->fetch_object($this->cursor_handle);
                /* should raise error about trying to create into an object that isn't defined */
            }
            $d = $this->fetchrow_hash();
            if (!$d) { return NULL; }
            foreach ($d as $k=>$v) {
                $r->$k = $v;
            }
            return $r;
        } else {
            return $this->dbh->dbd->fetch_object($this->cursor_handle);
        }
    }

    public function _stmt() {
        return $this->executed_stmt;
    }

    public function execution_time() {
        return $this->execution_time;
    }
}

class DBIdbh {
    public $dbd;
    public $dbname;
    private $outter_quotes;

    public function __construct($dbd) {
        $this->dbd = $dbd[0];
        $this->dbname = $dbd[1];
        $this->outter_quotes = !$this->dbd->quote_includes_enclosing();
    }

    public function quote_label($f) {
        return sprintf('`%s`', preg_replace('/`/', '', $f));
    }

    public function quote($value) {
        if (!isset($value)) {
            return 'NULL';
        }
        if (is_array($value)) {
            $ret = array();
            foreach ($value as $k=>$v) {
                $ret[$k] = $this->quote($v);
            }
            return $ret;
        }
        if (is_string($value) && is_numeric($value) && strval(intval($value)) === $value) {
            return intval($value);
        }
        if ($this->outter_quotes) {
            return "'".$this->dbd->quote($value)."'";
        }
        return $this->dbd->quote($value);
    }

    public function prepare($stmt=NULL) {
        if (!$stmt) {
            throw new DBIException("empty statement");
        }
        DBI::$query_count++;
        $sth = new DBIstatement($this, $stmt);
        return $sth;
    }

    public function do_() { // named thusly to avoid keyword conflict
        $a = func_get_args();
        $stmt = array_shift($a);
        $sth = $this->prepare($stmt);
        array_shift($a); # remove options, ignore for now
        $sth->execute_array($a);
        return $sth->affected_rows();
    }

    public function stats() {
        return sprintf("%d queries, %d rows fetched, %d seconds", DBI::$query_count, DBI::$fetchrow_count, DBI::$query_runtime);
    }

    public function tables() {
        return $this->dbd->tables();
    }

    public function table_info($table) {
        # should take arguments of $catalog, $schema, $table, $type
        return $this->dbd->table_info($table);
    }

    public function column_info($table, $column) {
        # should take arguments of $catalog, $schema, $table, $column
        return $this->dbd->column_info($table, $column);
    }
    
    public function dbname() {
    	return $this->dbd->dbname();
    }
}

class DBI {
    public static $query_count = 0;
    public static $fetchrow_count = 0;
    public static $query_runtime = 0;
    public static $statement_types = array();

    function getmicrotime(){
        return microtime(true);
    }

    public static function connect($type, $a = NULL) {
        if (!isset($a) && strpos($type, 'dbi') === 0) {
            # dbi:DriverName:database=database_name;host=hostname;port=port
            if (preg_match('/^dbi:(\w+):(.+)$/', $type, $m)) {
                $type = $m[1];
                $ax = split(';', $m[2]);
                $a = array();
                foreach ($ax as $x) {
                    if (preg_match('/^(\w+)=(.*)$/', $x, $m)) {
                        $a[$m[1]] = $m[2];
                    } else {
                        $a[$x] = true;
                    }
                }
            } else {
                throw new DBIException("unable to parse dsn $type");
            }
        }
        $dbdname = self::load_dbdriver($type);
        $dbres = call_user_func(array($dbdname, 'connect'), $a);
        if ($dbres) {
            $dbh = new DBIdbh(new $dbdname($dbres));
            return $dbh;
        }
        return NULL;
    }

    private function load_dbdriver($type) {
        if (!$type) {
            throw new DBIException("empty database driver specified");
        }
        $dbdname = "DBD$type";
        if (!class_exists($dbdname)) {
            $filename = "vanilla/dbdrivers/$type.dbd.php";
            if (file_exists($filename)) {
                require_once($filename);
                if (class_exists($dbdname)) {
                    return $dbdname;
                }
            }
        }
        throw new DBIException("unknown database abstraction $type ($dbdname)");
    }

}

class DBD {
    protected $dbres;

    public function __construct($dbres) {
        $this->dbres = $dbres;
    }

    public function escape($x) {
        die("you shouldn't use escape, use quote instead");
        return $this->quote($x);
    }
}

