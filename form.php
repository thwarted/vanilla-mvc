<?php

/* form.php - version 1.2
 * Requires PHP 5.1.6 with SPL
 * 
 * HTML form abstraction with the intent of integrating
 * forms into PHP Smarty templates easily
 *
 * Copyright 2007 Andy Bakun
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2 as 
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 *
 */

class formfield {
    protected $_name;
    protected $_value;
    protected $_defaultvalue;
    protected $_validationfunc;
    protected $_required;
    protected $_call_validator_always;
    protected $_originform;
    protected $_errormsg;
    protected $_valid;
    protected $_attr;
    protected $_label;
    protected $_notes;
    protected $_id;

    # $value is input type dependent, it could be an array for radio buttons or selects
    public function __construct($name, $value=NULL, $attr=array()) {
        $this->_name = $name;
        $this->_value = $value;
        $this->_attr = is_array($attr) ? $attr : array();

        $this->_required = NULL;
        $this->_valid = NULL;
        $this->_label = '';
        $this->_id = NULL;

        $this->post_construct();
    }

    protected function mkname() {
        if (is_object($this->_originform) && $fn = $this->_originform->name()) {
            return sprintf('%s[%s]', $fn, $this->_name);
        } else {
            return $this->_name;
        }
    }

    public function type() {
        $c = get_class($this);
        $c = preg_replace('/^form_(input_)?/', '', $c);
        $c = preg_replace('/_series$/', '', $c);
        return $c;
    }

    public function name() {
        return $this->_name;
    }

    public function origin_form($fm) {
        if (is_object($fm) && ($fm instanceof form)) {
            $this->_originform = $fm;
            $this->_id = NULL; # reset it, so it gets recalculated against the new form
        }
    }

    public function attributes($newattr = NULL) {
        if (isset($newattr)) {
            $this->_attr = $newattr;
        }
        return $this->_attr;
    }

    public function value($submitted_value = NULL) {
        if (isset($submitted_value)) {
            $this->_value = $submitted_value;
        }
        if (!isset($this->_value)) {
            return $this->_defaultvalue;
        }
        return $this->_value;
    }

    # this exists solely to let children override how their
    # submitted values are interpreted
    # with checkboxes, if they are unchecked, nothing is submitted
    # however, we don't want "nothing" to change the value of the field
    # when it is submitted
    public function submitted_value($sv = NULL) {
        return $this->value($sv);
    }

    public function default_value($newdefault = NULL) {
        if (isset($newdefault)) {
            $this->_defaultvalue = $newdefault;
        }
        return $this->_defaultvalue;
    }

    protected function post_construct() {
        return;
    }

    public function required($r = true) {
        $this->_required = $r;
        return $this;
    }

    public function verify_using($func, $callalways = false) {
        if (is_callable($func)) {
            $this->_validationfunc = $func;
            $this->_call_validator_always = $callalways;
        } else {
            if (is_array($func) && is_object($func[0])) {
                $fc = sprintf('(instance of) %s::%s', get_class($func[0]), $func[1]);
            } else {
                $fc = var_export($func, true);
            }
            throw new Exception("$fc is not callable");
        }
        return $this;
    }

    public function verify() {
        if (is_bool($this->_valid)) {
            return $this->_valid;
        }
        if (isset($this->_required) && $this->_required && isset($this->_value) && empty($this->_value)) {
            $this->_valid = false;
            $this->_errormsg = 'required';
        }
        if ((!isset($this->_valid) || $this->_call_validator_always) && $this->_validationfunc) {
            $r = call_user_func($this->_validationfunc, $this->_value, $this->_name, $this->_originform);
            if (!is_array($r) || count($r) != 3) {
                throw new Exception(var_dump($method, true)." did not return a three element array");
            }
            $this->_valid = $r[0] ? true : false; # convert to boolean
            $this->_value = $r[1];
            $this->_errormsg = $r[2];
        }
        if (!isset($this->_valid)) {
            $this->_valid = true;
        }
        return $this->_valid;
    }

    public function message($newerror = NULL) {
        if (isset($newerror)) {
            $this->_errormsg = $newerror;
        }
        return $this->_errormsg;
    }

    public function html_open() {
        return '';
    }

    public function html_close() {
        return '';
    }

    public function html() {
        return $this->html_open().$this->html_close();
    }

    public function label_html() {
        return sprintf('<label for="%s">%s</label>', $this->id(), $this->_label);
    }

    public function label_str() {
        return $this->_label;
    }

    public function id($newid = NULL) {
        if (isset($newid)) {
            $this->_id = $newid;
        }
        if (!isset($this->_id)) {
            if (is_object($this->_originform)) {
                $ofn = $this->_originform->name();
            } else {
                $ofn = uniqid();
            }
            $this->_id = sprintf('%s_%s', $ofn, $this->_name);
        }
        return $this->_id;
    }

    public function label($text) {
        $this->_label = $text;
        return $this;
    }

    public function notes($text = NULL) {
        $this->_notes = $text;
        return $this;
    }

    public function notes_html() {
        return $this->_notes;
    }

    protected function render_attributes($extra=NULL) {
        $r = '';
        foreach ($this->_attr as $an=>$av) {
            if (substr($an, 0, 1) != '_') {
                $r .= "$an=\"$av\" ";
            }
        }
        if ($id = $this->id()) {
            $r .= sprintf('id="%s" ', $id);
        }
        return $r;
    }
}

class form_input_text extends formfield {
    public function html() {
        $r = sprintf('<input type="text" name="%s" value="%s" ', $this->mkname(), $this->value());
        $r .= $this->render_attributes();
        $r .= '/>';
        return $r;
    }
}

class form_input_hidden extends formfield {
    public function html() {
        $r = sprintf('<input type="hidden" name="%s" value="%s" />', $this->mkname(), $this->value());
        return $r;
    }
}

class form_input_password extends formfield {
    public function html() {
        # password fields should never be populated
        $r = sprintf('<input type="password" name="%s" value="" ', $this->mkname());
        $r .= $this->render_attributes();
        $r .= '/>';
        return $r;
    }
}

class form_input_checkbox extends formfield {
    private $_value_when_checked;

    public function __construct($name, $value=NULL, $attr=array()) {
        parent::__construct($name, '', $attr);
        $this->_value_when_checked = $value;
    }

    public function html() {
        $checked = '';
        if ($this->value()) {
            $checked = 'checked="CHECKED"';
        }
        $r = sprintf('<input type="checkbox" name="%s" value="%s" %s ', $this->mkname(), $this->_value_when_checked, $checked);
        $r .= $this->render_attributes();
        $r .= '/>';
        if ($this->_label) {
            $r .= "<label for=\"".$this->id()."\">".$this->_label."</label>";
        }
        return $r;
    }
}

class form_textarea extends formfield {
    public function html() {
        $r = sprintf('<textarea name="%s" ', $this->mkname());
        $r .= $this->render_attributes();
        $r .= ">".$this->value()."</textarea>";
        return $r;
    }
}

class form_input_image extends formfield {
    # attributes should include a src value, or the template 
    # needs to use modify the output to include it
    # we don't check this in post_construct (like type attribute 
    # used for form_button) because the template needs to control
    # it
    public function html() {
        $r = sprintf('<input type="image" name="%s" ', $this->mkname());
        $r .= $this->render_attributes();
        $r .= '/>';
        return $r;
    }
}

class form_button extends formfield {
    private $_value_when_clicked;

    public function __construct($name, $value=NULL, $attr=array()) {
        parent::__construct($name, '', $attr);
        $this->_value_when_clicked = $value;
    }

    protected function post_construct() {
        if (!isset($this->_attr['type'])) {
            throw new Exception("field \"".$this->_name."\" not constructed with a \"type\" attribute");
        }
        if (!preg_match('/^submit|reset|button$/', $this->_attr['type'])) {
            throw new Exception("field \"".$this->_name."\" constructed with unknown type \"".$this->_attr['type']."\"");
        }
    }

    public function html_open() {
        $r = sprintf('<button name="%s" value="%s" ', $this->mkname(), $this->_value_when_clicked);
        $r .= $this->render_attributes();
        $r .= '>';
        return $r;
    }

    public function html_close() {
        return '</button>';
    }

    public function html() {
        if (!$this->_label) {
            throw new Exception("can not use form_button::html without calling ->label() first");
        }
        return $this->html_open().$this->_label.$this->html_close();
    }

    public function label_html() {
        # button labels are displayed on the button
        return '';
    }
}

class form_input_submit extends formfield {
    private $_value_when_clicked;

    public function __construct($name, $value=NULL, $attr=array()) {
        parent::__construct($name, '', $attr);
        $this->_value_when_clicked = $value;
    }

    public function html() {
        $r = sprintf('<input type="submit" name="%s" value="%s" ', $this->mkname(), $this->_value_when_clicked);
        $r .= $this->render_attributes();
        $r .= '/>';
        return $r;
    }
}

class form_input_reset extends formfield {
    public function html() {
        $r = '<input type="reset" ';
        $r .= $this->render_attributes();
        $r .= '/>';
        return $r;
    }
}

class form_input_radio_series extends formfield {
    protected $__options; 

    public function options($o) {
        if (is_array($o)) {
            $this->__options = $o;
        }
        return $this;
    }

    public function verify() {
        if (isset($this->_required) && $this->_required && (!isset($this->_value) || empty($this->_value))) {
            $this->_valid = false;
            $this->_errormsg = 'required';
        }
        return parent::verify();
    }

    public function html() {
        if (!isset($this->__options) || !is_array($this->__options)) {
            throw new Exception($this->_name."(".get_class($this).") does not have an options list");
        }
        $items = array();
        $c = 0;
        $id = $this->id();
        $value = $this->value();
        $elmname = $this->mkname();
        foreach ($this->__options as $v=>$l) {
            $c++;
            $iid = $id."_".$c;
            $checked = ($value == $v ? 'checked="checked"' : '');
            $i = sprintf('<input type="radio" name="%s" id="%s" value="%s" %s /><label for="%s">%s</label>', 
                                $elmname, $iid, $v, $checked, $iid, $l);
            $items[] = "   <li>$i</li>\n";
        }
        $r = "<ul style=\"margin-left: -30px; list-style: none;\" id=\"$id\">\n";
        $r .= join('', $items);
        $r .= "\n</ul>\n";
        return $r;
    }
}

class form_select_series extends form_input_radio_series {
    public function html() {
        if (!isset($this->__options) || !is_array($this->__options)) {
            throw new Exception($this->_name."(".get_class($this).") does not have an options list");
        }
        $items = array();
        $c = 0;
        $id = $this->id();
        $value = $this->value();
        foreach ($this->__options as $v=>$l) {
            $c++;
            $iid = $id."_".$c;
            $checked = ($value == $v ? 'selected="selected"' : '');
            $i = sprintf('<option id="%s" value="%s" %s >%s</option>', 
                                $iid, $v, $checked, $l);
            $items[] = "   $i\n";
        }
        $r = "<select name=\"".$this->mkname()."\" id=\"$id\">\n";
        $r .= join('', $items);
        $r .= "\n</select>\n";
        return $r;
    }
}

class form implements Countable, ArrayAccess, Iterator {
    private $_name;
    private $_datasource;
    private $_data;
    private $_submit_method;
    private $_submit_action;
    private $_fields;
    private $_message;
    private $_error_count;
    private $_processed = false;

    public function __construct($name, $datasource='REQUEST') {
        $this->_name = $name;
        $this->message = '';
        $this->_error_count = 0;

        $datasource = strtolower($datasource);
        switch($datasource) {
            case 'get':
                $this->_datasource = &$_GET;
                $this->method('get');
                break;
            case 'post':
                $this->_datasource = &$_POST;
                $this->method('post');
                break;
            case 'request':
                $this->_datasource = &$_REQUEST;
                $this->method('post');
                break;
            default:
                throw new Exception("unknown datasource $datasource for form $name");

        }

        if (!empty($this->_datasource[$name]) && is_array($this->_datasource[$name])) {
            $this->_data = $this->_datasource[$name];
            $this->_datasource[$name.'-consumed'] = $this->_datasource[$name];
            unset($this->_datasource[$name]);
        } else {
            $this->_data = false;
        }

    }

    public function name($newname=NULL) {
        $r = $this->_name;
        if (isset($newname)) {
            $this->_name = $r;
        }
        return $r;
    }

    public function message($newmsg = NULL) {
        if (isset($newmsg)) {
            $this->_message = $newmsg;
        }
        return $this->_message;
    }

    public function submitted() {
        return is_array($this->_data);
    }

    public function dump() {
        $x = array();
        foreach ($this as $fn=>$o) {
            $x[$fn] = $o->value();
        }
        return var_export(array('name'=>$this->_name, 'fields'=>$x, 'submitted'=>is_array($this->_data)), true);
    }

    public function method($m) {
        if (preg_match('/^get|post$/i', $m)) {
            $this->_submit_method = strtolower($m);
        } else {
            throw new Exception("$m is not an acceptable form submission method for form ".$this->name);
        }
    }

    public function action($a=NULL) {
        $r = $this->_submit_action;
        if (isset($a)) {
            $this->_submit_action = $a;
        }
        return $r;
    }
    
    public function has_errors() {
        return ($this->_error_count != 0);
    }

    public function force_failure() {
        $this->_error_count = -1;
    }

    public function verify() {
        if ($this->_error_count < 0) {
            # failure was forced
            return false;
        }
        $this->_error_count = 0;
        if (is_array($this->_data)) {
            foreach ($this as $name=>$field) {
                if (!$field->verify()) {
                    $this->_error_count++;
                }
            }
        }
        return !($this->_error_count);
    }

    public function processed($n = NULL) {
        if (isset($n)) {
            $this->_processed = $n;
        }
        return $this->_processed;
    }

    public function start() {
        $r = '<form id="'.$this->_name.'" method="'.$this->_submit_method.'"';
        if ($this->_submit_action) {
            $r .= ' action="'.$this->_submit_action.'"';
        }
        $r .= '>';
        return $r;
    }

    public function end() {
        $r = '';
        foreach ($this->_fields as $fname=>$i) {
            if ($i instanceof form_input_hidden) {
                $r .= $i->html();
            }
        }
        $r .= '<input type="hidden" name="form" value="'.$this->_name.'" /></form>';
        return $r;
    }

    # Countable interface
    public function count() {
        return count($this->_fields);
    }

    # ArrayAccess interface
    public function offsetExists($offset) {
        return (isset($this->_fields[$offset]) && is_object($this->_fields[$offset]));
    }

    public function offsetGet($offset) {
        if (!isset($this->_fields[$offset]) || !is_object($this->_fields[$offset])) {
            error_log("field \"$offset\" does not exist in form \"".$this->_name."\"");
            return new formfield('empty');
        }
        return $this->_fields[$offset];
    }

    public function offsetSet($offset, $value) {
        # we purposely ignore the offset value
        if (is_object($value) && ($value instanceof formfield)) {
            $value->origin_form($this);
            $fieldname = $value->name();
            if (is_array($this->_data)) { # if the form was actually submitted
                $value->submitted_value(isset($this->_data[$fieldname]) ? $this->_data[$fieldname] : NULL);
            }
            $this->_fields[$fieldname] = $value;
        }
    }

    public function offsetUnset($offset) {
        unset($this->_fields[$offset]);
    }

    # Iterator interface
    public function current() { return current($this->_fields); }
    public function rewind() { return reset($this->_fields); }
    public function key() { return key($this->_fields); }
    public function next() { return next($this->_fields); }
    public function valid() { return current($this->_fields) ? true : false; }

}

?>
