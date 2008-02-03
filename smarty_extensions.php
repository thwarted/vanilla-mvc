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

require_once "vanilla/lib.php";

class smarty_extensions {

    public function func_find($params, &$smarty) {
        if (empty($params['var'])) {
            return;
        }
        if (empty($params['model'])) {
            $smarty->trigger_error("find: missing 'model' parameter");
            return;
        }
        $model = $params['model'];
        if (!file_exists("models/$model.php")) {
            $smarty->trigger_error("find: 'model' $model missing file");
            #throw new HTTPNotFound("model $model file not found");
            return;
        }
        require_once "models/$model.php";
        # FIXME needs to be converted to use the model('x')->find syntax
        if (!class_exists($model)) {
            $smarty->trigger_error("find: 'model' $model missing class");
            #throw new HTTPNotFound("model $model class not found");
            return;
        }
        if (method_exists($model, 'find')) {
            $x = call_user_func_array(array($model, 'find'), array($params['cond']));
            $smarty->assign($params['var'], $x);
        }
    }

    public function func_debugbox($params, &$smarty) {
        if (!lib::$debugboxshown) {
            return lib::debugbox();
        }
        return '';
    }

    public function prefilter_convert_loop_breaks($tplsource, &$smarty) {
        return preg_replace('/\{break\}/', '{php}break;{/php}', $tplsource);
    }

    public function prefilter_convert_loop_continue($tplsource, &$smarty) {
        return preg_replace('/\{continue\}/', '{php}continue;{/php}', $tplsource);
    }

    public function dump_array($value, $level = -1, $html = 1) {
        $x = var_export($value, true);
        if ($html) {
            $x = "<pre>".htmlspecialchars($x)."</pre>";
        }
        return $x;
    }

    public function modifier_printr($v) {
        return smarty_extensions::dump_array($v, -1, 0); # no html
    }

    public function modifier_join($v, $sep) {
        if (is_array($v)) {
            return join($v, $sep);
        }
        return $v;
    }

    public function modifier_reverse($v) {
        if (is_array($v)) {
            return array_reverse($v);
        } else {
            return strrev("$v");
        }
    }

    public function modifier_dumpform($v) {
        if (is_object($v) && is_a($v,'form')) {
            $a = array();
            foreach($v as $fn=>$fo) {
                $a[$fn] = $fo->type();
            }
            d($a);
        }
    }

    public function modifier_slice($v, $o, $l = NULL, $p = NULL) {

        if (!empty($p))
            $p = 'false';

        if (!empty($l))  {
            $new = array_slice($v, $o, $l, $p);
        } else {
            $new = array_slice($v, $o);
        }
        return $new;
    }

    public function modifier_attr($v, $attrname, $attrvalue) {
        $attrvalue = htmlspecialchars($attrvalue);
        if (preg_match("/$attrname=/", $v)) {
            # replace current ones
            $v = preg_replace("/($attrname=['\"]?)([^'\"]+)(['\"]?)/", "$attrname=\"$attrvalue\"", $v);
        } else {
            # add new
            if (preg_match('/^<(\w+)\s+(.+)>$/s', $v, $m)) {
                list($all, $pre, $post) = $m;
                $v = "<$pre $attrname=\"$attrvalue\" $post>";
            }
        }
        return $v;
            #list($all, $open, $rest) = $m;
            #$v = "$open $attrname=\"$attrvalue\" $rest";
    }

    public function modifier_count($v) {
        return count($v);
    }

    public function modifier_d($v) {
        # record the value as debugging info to appear in the messages box
        d($v);
    }

    public function modifier_safeattr($v) {
        $v = preg_replace('/[\s\t\r\n]+/', ' ', preg_replace('/["\'><]/', '', $v));
        return $v;
    }

    public function modifier_style($v, $style) {
        return smarty_extensions::modifier_attr($v, 'style', $style);
    }

    public function modifier_value($v, $value) {
        return smarty_extensions::modifier_attr($v, 'value', $value);
    }

    public function modifier_id($v, $value) {
        return smarty_extensions::modifier_attr($v, 'id', $value);
    }


}

