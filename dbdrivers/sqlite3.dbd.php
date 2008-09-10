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

require_once "vanilla/dbdrivers/abstract.pdo.dbd.php";

class DBDsqlite3 extends DBDPDO {
    static public function connect($a) {
        $db1 = new PDO('sqlite:'.$a['dbname']);
        # this should be a passable option
        $db1->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $db1;
    }
    public function dbname() {
        return $this->connect_options['dbname'];
    }
    public function tables() {
        $c = $this->dbres->prepare("select name from sqlite_master where type = 'table'");
        $c->execute();
        $r = array();
        while($x = $c->fetch(PDO::FETCH_NUM)) {
            $r[] = $x[0];
        }
        return $r;
    }
    public function table_info($table) {
        $c = $this->dbres->prepare("PRAGMA table_info(".$table.")");
        $c->execute();
        $r = array();
        while($x = $c->fetch(PDO::FETCH_ASSOC)) {
            $x['Field'] = $x['name'];
            $x['Type'] = $x['type'];
            $x['Default'] = $x['dflt_value'];
            $x['Null'] = !($x['notnull']) ? 'YES' : 'NO';
            $x['Key'] = $x['pk'] ? 'PRI' : '';
            $x['Extra'] = '';
            $r[] = $x;
        }
        return $r;
    }
    public function column_info($table, $column) {
        throw new Exception("unimplmented");
    }
}

