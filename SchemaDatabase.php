<?php
/* Copyright 2005-2007 Andrew A. Bakun
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

require_once "vanilla/modellib.php";
require_once "vanilla/SchemaTable.php";
require_once "vanilla/SchemaColumn.php";
require_once "vanilla/ModelCollection.php";
require_once "vanilla/Model.php";

class SchemaDatabase {
    static $activedb = NULL;

    public $name;
    public $nameQ;

    public $REtables = '';

    public $tables = array();

    public function __construct($name) {
        global $dbh;

        $this->nameQ = $dbh->quote_label($name);
        $this->___find_tables();

        $this->___find_references();
    }

    public static function register_schema($sdb) {
        $c = get_class();
        if ($sdb instanceof $c) {
            self::$activedb = $sdb;
        } else {
            throw new Exception("argument 1 to ".get_class()."::register_schema is not a ".get_class());
        }
    }

    public function dump() {
        foreach ($this->tables as $t) {
            print $t->dump();
            print "\n";
        }
    }

    private function ___find_tables() {
        global $dbh;

        $sth = $dbh->prepare("show tables from ".$this->nameQ);
        $sth->execute();
        while(list($tn) = $sth->fetchrow_array()) {
            $this->tables[$tn] = new SchemaTable($this, $tn);
            if (!class_exists($tn)) {
                eval("class $tn extends Model { }");
            }
        }

        /*
        #$tx = 'product,product_image,image,imgdata,envshot,product_envshot,role,user';
        #$tx .= ',user,role,personal_info,trade_reference';
        #$tx = 'user,email_outgoing';
        foreach (preg_split('/,/', $tx) as $tn) {
            $this->tables[$tn] = new SchemaTable($this, $tn);
            if (!class_exists($tn)) {
                eval("class $tn extends Model { }");
            }
        }
        */

        $this->REtables = sprintf('/^((\w+_)??(%s))_id$/', join('|', array_keys($this->tables)));

        foreach ($this->tables as $name=>$t) {
            $t->___find_columns();
        }

    }

    private function ___find_references() {
        foreach ($this->tables as $name=>$t) {
            $t->___find_references();
        }

        foreach ($this->tables as $name=>$t) {
            $t->___create_join_columns();
        }
        foreach ($this->tables as $name=>$t) {
            $t->___create_through_joins();
        }
    }

    public function model($n) {
        return @ $this->tables[$n];
    }
}

