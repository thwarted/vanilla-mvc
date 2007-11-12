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

class SchemaColumn {
    public $table;
    public $name;
    public $nameQ;
    public $type;
    public $class;
    public $nullable;
    public $default;
    public $references;
    public $source;
    public $virtual = false;
    public $collectionsql;
    public $through;
    public $ignore = false;
    public $is_primary = false;

    public function __construct($table, $name) {
        $this->table = $table;
        $this->name = $name;
        $this->nameQ = $this->table->db->dbhandle->quote_label($name);
    }

    public function dump() {
        $r = new stdClass;
        $r->FieldName = $this->name;
        $r->table = $this->table->name;
        $r->type = $this->type;
        $r->class = $this->class;
        $r->references = $this->references;
        $r->source = $this->source;
        $r->through = $this->through;
        $r->collectionsql = $this->collectionsql;
        return $r;
    }
}

