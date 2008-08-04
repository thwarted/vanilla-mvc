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

abstract class DBDPDO extends DBD {
    abstract static public function connect($a);
    public function quote_includes_enclosing() { return true; }
    public function quote($x) {
        return $this->dbres->quote($x);
    }
    public function query($stmt) {
        $c = $this->dbres->prepare($stmt);
        # this needs to be revisisted
        # throw a better exception, use DBIException class
        if (!$c) {
            $msg = join("\n", $this->dbres->errorInfo());
            $e = new DBIException($msg);
            $e->setStatement($stmt);
            throw $e;
        }
        $c->execute();
        return $c;
    }
    public function errno() {
        # FIXME, should return an integer description, errorCode looks like HYxxxx
        return $this->dbres->errorCode();
    }
    public function error() {
        # FIXME, should return a string description
        return $this->dbres->errorCode();
    }
    public function num_rows($ch) {
        # there does not appear to be a PDO function for this, 
        # ->rowCount is the wrong one (works on non-selects)
        return $ch->rowCount();
    }
    public function affected_rows($ch) {
        return $ch->rowCount();
    }
    public function insert_id() {
        return $this->dbres->lastInsertId();
    }
    public function free_result($x) {
        return $x->closeCursor();
    }
    public function fetch_row($ch) {
        return $ch->fetch(PDO::FETCH_NUM);
    }
    public function fetch_hash($ch) {
        return $ch->fetch(PDO::FETCH_ASSOC);
    }
    public function fetch_object($ch, $cn=NULL) {
        if (isset($cn) && class_exists($cn)) {
            $d = $this->fetch_hash($ch);
            if (!$d) { return NULL; }
            $x = new $cn();
            foreach ($d as $k=>$v) {
                $x->$k = $v;
            }
        } else {
            $x = $ch->fetch(PDO::FETCH_OBJ);
        }
        return $x;
    }
    abstract public function tables();
    abstract public function table_info($table);
    abstract public function column_info($table, $column);
}

