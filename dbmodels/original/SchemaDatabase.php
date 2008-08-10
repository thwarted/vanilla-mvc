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

class SchemaDatabase {
    public $dbhandle;
    public $name;
    public $nameQ;
    private $builtclasses = array();
    private $setupcode = array();

    public $REtables = '';

    public $tables = array();

    public function __construct($name, $dbhandle, $options=array()) {
        $this->dbhandle = $dbhandle;

        $this->nameQ = $this->dbhandle->quote_label($name);
        $this->___find_tables_build_models($options);

        $this->___find_references();
    }

    public function dump() {
        foreach ($this->tables as $t) {
            print $t->dump();
            print "\n";
        }
    }

    public function init($dbhandle) {
        # to be called after being unserialized
        # FIXME use __wakeup() here?
        $this->dbhandle = $dbhandle;
        foreach ($this->builtclasses as $tn=>$code) {
            if (!class_exists($tn)) {
                eval($code);
            }
        }
        foreach ($this->setupcode as $tn=>$code) {
            $ST = $this->tables[$tn];
            eval($code);
        }
    }

    private function ___find_tables_build_models($options) {
        $ModelCommonCode = file_get_contents('vanilla/dbmodels/original/ModelCommonCode.php');
        # this could potentially save some space in the cached/serialized SchemaDatabase object
        #$ModelCommonCode = preg_replace('/#[^\n]*\n/', '', $ModelCommonCode);

        $ignore = @ $options['ignore'];
        if (!$ignore) $ignore = array();
        else $ignore = preg_split('/\s*,\s*/', $ignore);

        $ignorere = @ $options['ignorere'];

        $tables = $this->dbhandle->tables();
        while($tn = array_shift($tables)) {
            if (in_array($tn, $ignore)) continue;
            if ($ignorere) {
                if (preg_match($ignorere, $tn)) continue;
            }
            $cn = $tn;
            if (isset($options['table_match_re'])) {
                if (!preg_match($options['table_match_re'], $tn))
                    continue;

                $cn = preg_replace($options['table_match_re'], '$2', $tn);
            }
            $ST = new SchemaTable($this, $tn, $cn);
            $this->tables[$cn] = $ST;
            if (!class_exists($cn)) {
                #error_log("creating class $tn");
                $code = 'class '.$cn.' extends Model { '.$ModelCommonCode.' }';
                eval($code);
                $this->builtclasses[$cn] = $code;
            }
            $cv = get_class_vars($cn);
            if (!array_key_exists('__table__', $cv)) {
                throw new Exception('pre-defined Model class "'.$cn.'" does not have $__table__ as a public static member');
            }
            $code = sprintf('%s::$__table__ = $ST;', $cn);
            eval($code);
            $this->setupcode[$cn] = $code;
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

