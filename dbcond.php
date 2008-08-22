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

class cond {
    public $op;
    public $val;

    public function __construct($op, $val=NULL) {
        $this->op = $op;
        $this->val = $val;
    }
    public function expr() {
        $x = call_user_func(array($this, $this->op."_expr"), uniqid());
        return $x;
    }

    public static function like() { $x = func_get_args(); return new cond('like', array_values_recursive($x)); }
    private function like_expr($l) { return array("like ?:$l", array($l=>$this->val[0])); }

    public static function notlike() { $x = func_get_args(); return new cond('notlike', array_values_recursive($x)); }
    private function notlike_expr($l) { return array("not like ?:$l", array($l=>$this->val[0])); }

    public static function in() { $x = func_get_args(); return new cond('in', array_values_recursive($x)); }
    private function in_expr($l) { return array("in (?:$l:join)", array("$l:join"=>$this->val)); }

    public static function notin() { $x = func_get_args(); return new cond('notin', array_values_recursive($x)); }
    private function notin_expr($l) { return array("not in (?:$l:join)", array("$l:join"=>$this->val)); }

    public static function equal($x) { return new cond('equal', $x); }
    private function equal_expr($l) { return array("= ?:$l", array($l=>$this->val)); }

    public static function notequal($x) { return new cond('notequal', $x); }
    private function notequal_expr($l) { return array("!= ?:$l", array($l=>$this->val)); }

    public static function between($x, $y) { return new cond('between', array('min'=>$x, 'max'=>$y)); }
    private function between_expr($l) { return array("between ?:min$l and ?:max$l", array("min$l"=>$this->val['min'], "max$l"=>$this->val['max'])); }

    public static function notbetween($x, $y) { return new cond('notbetween', array('min'=>$x, 'max'=>$y)); }
    private function notbetween_expr($l) { return array("not between ?:min$l and ?:max$l", array("min$l"=>$this->val['min'], "max$l"=>$this->val['max'])); }

    public static function lt($x) { return new cond('lt', array('x'=>$x)); }
    private function lt_expr($l) { return array("< ?:x$l", array("x$l"=>$this->val['x'])); }

    public static function ltequal($x) { return new cond('ltequal', array('x'=>$x)); }
    private function ltequal_expr($l) { return array("<= ?:x$l", array("x$l"=>$this->val['x'])); }

    public static function gt($x) { return new cond('gt', array('x'=>$x)); }
    private function gt_expr($l) { return array("> ?:x$l", array("x$l"=>$this->val['x'])); }

    public static function gtequal($x) { return new cond('gtequal', array('x'=>$x)); }
    private function gtequal_expr($l) { return array(">= ?:x$l", array("x$l"=>$this->val['x'])); }

    public static function nullsafeequal($x) { return new cond('nullsafeequal', array('x'=>$x)); }
    private function nullsafeequal_expr($l) { return array("<=> ?:x$l", array("x$l"=>$this->val['x'])); }
    
    public static function isnull() { return new cond('isnull'); }
    private function isnull_expr($l) { return array("is null", array()); }
    
    public static function notnull() { return new cond('notnull'); }
    private function notnull_expr($l) { return array("is not null", array()); }
}

