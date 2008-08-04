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

class DBDmysql extends DBD {
    public function connect($a) {
        if (!empty($a['persistent'])) {
            $db1 = mysql_pconnect($a['host'], $a['user'], $a['password']);
        } else {
            $db1 = mysql_connect($a['host'], $a['user'], $a['password']);
        }
        if ($db1 && !empty($a['database'])) {
            mysql_select_db($a['database'], $db1);
        }
        return $db1;
    }
    public function quote_includes_enclosing() { return false; }
    public function quote($x) {
        return mysql_real_escape_string($x, $this->dbres);
    }
    public function query($stmt) {
        return mysql_query($stmt, $this->dbres);
    }
    public function errno() {
        return mysql_errno($this->dbres);
    }
    public function error() {
        return mysql_error($this->dbres);
    }
    public function num_rows() {
        return mysql_num_rows($this->dbres);
    }
    public function affected_rows() {
        return mysql_affected_rows($this->dbres);
    }
    public function insert_id() {
        return mysql_insert_id($this->dbres);
    }
    public function free_result($x) {
        if (is_resource($x)) {
            return mysql_free_result($x);
        }
    }
    public function fetch_row($ch) {
        return mysql_fetch_row($ch);
    }
    public function fetch_hash($ch) {
        return mysql_fetch_assoc($ch);
    }
    public function fetch_object($ch, $cn=NULL) {
        if (isset($cn) && class_exists($cn)) {
            return mysql_fetch_object($ch, $cn);
        }
        return mysql_fetch_object($ch);
    }
    public function tables() {
        $q = mysql_query("show tables", $this->dbres);
        $r = array();
        while($x = mysql_fetch_row($q)) {
            $r[] = $x[0];
        }
        mysql_free_result($q);
        return $r;
    }
    public function table_info($table) {
        $q = mysql_query(sprintf('desc `%s`', $table));
        $r = array();
        while ($x = mysql_fetch_assoc($q)) {
            $r[] = $x;
        }
        mysql_free_result($q);
        return $r;
    }
    public function column_info($table, $column) {
        throw new Exception("unimplmented");
    }
}

