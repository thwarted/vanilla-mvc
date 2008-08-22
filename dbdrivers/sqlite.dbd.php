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

class DBDsqlite extends DBD {
	static public $database_name;
	
    public function connect($a) {
    	self::$database_name = $a['dbname'];    	
        if (!empty($a['persistent'])) {
            $db1 = sqlite_popen($a['dbname']);
        } else {
            $db1 = sqlite_open($a['dbname']);
        }
        return $db1;
    }
    public function quote_includes_enclosing() { return false; }
    public function quote($x) {
        return sqlite_escape_string($x);
    }
    public function query($stmt) {
        return sqlite_query($stmt, $this->dbres);
    }
    public function errno() {
        return sqlite_last_error($this->dbres);
    }
    public function error() {
        return sqlite_error_string(sqlite_last_error($this->dbres));
    }
    public function num_rows() {
        return sqlite_num_rows($this->dbres);
    }
    public function affected_rows() {
        return sqlite_changes($this->dbres);
    }
    public function insert_id() {
        return sqlite_last_insert_rowid($this->dbres);
    }
    public function free_result($x) {
        return;
    }
    public function fetch_row($ch) {
        return sqlite_fetch_array($ch, SQLITE_NUM);
    }
    public function fetch_hash($ch) {
        return sqlite_fetch_array($ch, SQLITE_ASSOC);
    }
    public function fetch_object($ch, $cn=NULL) {
        if (isset($cn) && class_exists($cn)) {
            return sqlite_fetch_object($ch, $cn);
        }
        return sqlite_fetch_object($ch);
    }
    public function dbname() {
    	return self::$database_name;
    }
}

