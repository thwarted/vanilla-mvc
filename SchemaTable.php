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

class SchemaTable {
    public $db;
    public $name;
    public $nameQ;

    public $pk = array();
    public $columns = array();
    public $virtual = array();

    public function __construct($db, $name) {
        $this->db = $db;
        $this->name = $name;
        $this->nameQ = $this->db->dbhandle->quote_label($name);
    }

    public function dump() {
        $x = array();
        foreach ($this->columns as $f=>$c) {
            $l = sprintf('   %s %s', $c->nameQ, $c->type);
            if ($c->references) {
                $l .= sprintf("\n         references a %s", $c->references);
                $l .= sprintf("\n         value from (self).%s", $c->source);
            }
            $x[$c->nameQ] = $l;
        }
        foreach ($this->virtual as $f=>$c) {
            if ($c->source) {
                $l = sprintf("   %s %s\n         by key (self).%s", $c->nameQ, $c->class, $c->source);
            } elseif ($c->collectionsql) {
                # see ONE-OR-MANY-CHECK in Model.php
                $l = sprintf("   %s collection of %s\n         by query %s", $c->nameQ, $c->class, $c->collectionsql);
            } else {
                $l = sprintf('   %s is UNKNOWN', $c->nameQ);
            }
            $x[$c->nameQ] = $l;
        }
        return "table ".$this->name."\n".join("\n", $x)."\n";
    }

    private function ___collist($tabalias=NULL, $fullcolalias=false) {
        if (!isset($tabalias)) {
            $tabalias = $this->nameQ;
        }
        $r = array();
        foreach ($this->columns as $name=>$column) {
            $x = sprintf('%s.%s', $tabalias, $column->nameQ);
            if ($fullcolalias) {
                $x .= " as ".$this->db->dbhandle->quote_label('_'.$this->name.'___'.$column->name);
            }
            $r[] = $x;
        }
        return join(', ', $r);
    }

    public function _conditions_to_query($cond) {
        if (strval(intval($cond)) === "$cond") {
            # simple integer key
            $a = array('id'=>$cond);
            $where = sprintf('%s.%s = ?:id', $this->nameQ, $this->pk);
        } elseif (is_array($cond)) {
            # where clause and an array of substitutions
            if (count($cond) == 2 && isset($cond[0]) && isset($cond[1]) && is_string($cond[0]) && is_array($cond[1])) {
                $where = $cond[0];
                $a = $cond[1];
            } else {
                $a = array();
                $where = array();
                # $f could be an SQL expression, don't quote it
                foreach ($cond as $f=>$v) {
                    $x = uniqid();
                    if (preg_match('/^\w+$/', $f)) {
                        $f = $this->db->dbhandle->quote_label($f);
                    }
                    if (is_array($v)) {
                        $x .= ":join";
                        $where[] = sprintf('%s in (?:%s)', $f, $x);
                    } else {
                        $where[] = sprintf('%s = ?:%s', $f, $x);
                    }
                    $a[$x] = $v;
                }
                $where = join(' and ', $where);
            }
        } else {
            throw new Exception("illegal type for conditions to ".$this->name."::find");
        }
        return array($where, $a);
    }

    public function find($cond=NULL, $limit=NULL) {
        if (empty($cond)) return array();

        if (!empty($limit) && !preg_match('/^\d+(,\d+)?$/', $limit)) {
            # FIXME report a better error here
            error_log("invalid limit $limit");
            return array();
        }

        list($where, $a) = $this->_conditions_to_query($cond);

        if (preg_match('/^\s*select/', $where)) {
            $q = $where;
        } else {
            $q = "select ".$this->___collist()." from ".$this->nameQ." where $where";
        }
        if ($limit) {
            $q .= " limit $limit";
        }
        $sth = $this->db->dbhandle->prepare($q);
        $sth->execute($a);
        #error_log($sth->_stmt());
        $r = array();
        $PK = $this->pk;
        $class = $this->name;
        while($o = $sth->fetchrow_object($class)) {
            $o = _object_cache::singleton($o, $o->$PK, $class);
            $o->checkpoint();
            $r[] = $o;
        }
        return $r;
    }

    public function find_first($cond) {
        # FIXME optimize this by checking if $cond is an integer
        # and seeing if the object already exists in _object_cache
        $x = $this->find($cond, 1);
        if (empty($x)) return NULL;
        return $x[0];
    }

    public function ___find_columns() {

        $sth = $this->db->dbhandle->prepare("desc ".$this->nameQ);
        $sth->execute();
        while($r = $sth->fetchrow_hashref()) {
            $c = new SchemaColumn($this, $r['Field']);

            if ($r['Key'] === 'PRI') {
                $this->pk[] = $r['Field'];
                $c->is_primary = true;
            }

            $c->name = $r['Field'];
            $c->type = $r['Type'];
            $c->nullable = !empty($r['Null']);
            $c->default = $r['Default'];
            $c->virtual = false;

            $this->columns[$c->name] = $c;
        }
        # we only track one field as the primary key
        # if the primary key is composed of more
        # than one field, it is useless to us
        if (count($this->pk) == 0) {
            #error_log("WARNING: unsupported configuration: ".$this->name." does not define a primary key");
        } elseif (count($this->pk) > 1) {
            #error_log("WARNING: unsupported configuration: ".$this->name." has a primary key composed of more than one column");
            $this->pk = NULL;
        } else {
            $this->pk = $this->pk[0];
        }
    }

    public function ___find_references() {
        foreach ($this->columns as $name=>$field) {
            if (preg_match($this->db->REtables, $name, $m)) {
                list($junk, $virtual, $junk, $foreigntab) = $m;
                $refersto = false;
                if (isset($this->db->tables[$foreigntab])) {
                    $ft = $this->db->tables[$foreigntab];
                    if (isset($ft->columns['id'])) {
                        $refersto = $ft->name;
                    }
                }
                if (!empty($refersto)) {
                    $field->source = $virtual;
                    $field->references = $refersto;
                    $v = new SchemaColumn($this, $virtual);
                    $v->class = $refersto;
                    $v->source = $name;
                    $v->virtual = true;
                    $this->virtual[$virtual] = $v;
                    #error_log("(1) adding virtual column ".$this->name.".".$virtual);
                } else {
                    error_log("unable to find fk reference for ".$this->name.".$name\n");
                }
            }
        }
    }

    public function ___create_join_columns() {
        /* 
        for every MODEL virtual-column VC
            TB = table named VC-name
            if TB then
                TB.virtual collection of MODEL
                    by MODEL.(VC.source) = TB.id
        */
        foreach ($this->virtual as $name=>$field) {
            $tb = @ $this->db->tables[$name];
            if ($tb) {
                # if there is already a virtual column defined with the same name
                # it has higher priority because the data for it came from the 
                # table itself, not a foreign reference
                if (isset($tb->virtual[$this->name])) {
                    #error_log("already defined virtual column $name.".$this->name);
                    continue;
                }
                if (isset($tb->columns[$this->name])) {
                    #error_log("already defined column $name.".$this->name);
                    continue;
                }
                $v = new SchemaColumn($tb, $this->name);
                $v->class = $this->name;
                $v->virtual = true;
                $v->collectionsql = $this->db->dbhandle->quote_label($field->source)." = ?:id";
                $tb->virtual[$this->name] = $v;

                #error_log("(2) adding virtual column ".$tb->name.".".$this->name);
            }
        }
    }

    public function ___create_through_joins() {
        foreach ($this->virtual as $name=>$field) {
            # does the virtual column name ($name) match another table?
            $re = sprintf('/^((\w+)_%s|%s_(\w+))/', $this->name, $this->name);
            if (preg_match($re, $name, $m)) {
                @ list($all, $thrutbname, $p1, $p2) = $m;
                $asctbname = empty($p1) ? $p2 : $p1;
                #error_log("found table $asctbname for ".$this->name." through $thrutbname");

                # found a match by name, now see if $throughtable has foreign key to $asctbname
                $thrutb = @ $this->db->tables[$thrutbname];
                $asctb = @ $this->db->tables[$asctbname];

                if (!$thrutb || !$asctb) continue;

                if (isset($thrutb->virtual[$asctbname])) {

                    # we're going to add a column, issue a warning if one with that name already exists
                    if (isset($this->columns[$asctbname])) {
                        error_log($this->name." already defines a column named $asctbname");
                    }
                    if (isset($this->virtual[$asctbname])) {
                        error_log($this->name." already defines a virtual column named $asctbname");
                    }

                    $c = new SchemaColumn($this, $asctbname);
                    $c->class = $asctbname;
                    $c->through = $thrutbname;
                    $c->virtual = true;

                    #print_r(array($thrutb->virtual[$asctbname]->dump()));
                    #print_r(array($thrutb->virtual[$this->name]->dump()));
                    #       asctb        asctb            thrutb          asctb      thrutab                      thrutb
                    #select image.* from image left join product_image on image.id =product_image.image_id where product_image.product_id = 10;
                    $c->collectionsql = sprintf('select %s.* from %s left join %s on %s.%s = %s.%s where %s.%s = ?:id',
                            $asctb->nameQ,   # column listing
                            $asctb->nameQ,   # from
                            $thrutb->nameQ,  # join
                            $asctb->nameQ,   # pkT
                            $asctb->pk,      # pkC
                            $thrutb->nameQ,   # fkT
                            $thrutb->virtual[$asctbname]->source, # fkC
                            $thrutb->nameQ,   # whereT
                            $thrutb->virtual[$this->name]->source
                    );
                    $this->virtual[$asctbname] = $c;
                    #$this->virtual[$thrutbname]->ignore = true;
                }
            }
        }
    }
}

