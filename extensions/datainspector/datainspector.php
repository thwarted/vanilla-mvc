<?php

# This code is highly introspective into the Model objects

class controller__datainspector extends base_controller {
    static public $inspector_path = '';
    static private $last_fieldname = '';
    static private $last_objlink = '';

    public function _render() {
        $this->view->template_dir = "./vanilla/extensions/datainspector/views";
        $this->view->register_modifier('objlink', array('controller__datainspector', 'smarty_modifier_objlink'));
        $this->view->register_modifier('notefieldname', array('controller__datainspector', 'smarty_modifier_notefieldname'));
        $this->view->register_modifier('noteobjlink', array('controller__datainspector', 'smarty_modifier_noteobjlink'));
        $this->view->register_modifier('getimageobj', array('controller__datainspector', 'smarty_modifier_getimageobj'));
        return parent::_render();
    }

    public function _in_valid_context($request=array()) {
        if (count($request) < 3) {
            throw new HTTPNotFound();
        }
        list($method, $class, $pk) = $request;

        if (!class_exists($class)) {
            throw new HTTPNotFound();
        }
        if (!is_subclass_of($class, 'Model')) {
            throw new HTTPNotFound();
        }
    }

    # the "build_form_*" API is undocumented, non-standard
    # and really needs more consideration
    public function form_($class, $formname) {
        if (!class_exists($class)) {
            throw new HTTPNotFound();
        }
        $fn = "build_form_$formname";
        if (!method_exists($class, $fn)) {
            throw new HTTPNotFound();
        }
        $code = sprintf('return %s::build_form_%s();', $class, $formname);
        $form = eval($code);
        if ($form instanceof form) {
            $this->view->assign('class', $class);
            $this->view->assign('name', $formname);
            $this->view->assign('form', $form);
            $this->viewname = "form.tpl";
        } else {
            throw new HTTPNotFound();
        }
    }

    public function object_($class=NULL, $pk=NULL) {
        if (isset($_GET['path'])) {
            self::$inspector_path = base64_decode($_GET['path']);
        } else {
            #$this->path = sprintf('<a href="%s">$%s</a>', url($this, 'object', $class, $pk), $class);
            self::$inspector_path = '$'.$class;
        }

        # FIXME there should be an interface in vanilla to get this
        $modelTable = eval("return ".$class.'::$__table__;');
        $pkcol = $modelTable->pk;
        $obj = call_user_func(array($modelTable, 'find_first'), $pk);

        if (!is_object($obj)) {
            throw new HTTPNotFound();
        }

        # WARNING: assumes integer primary keys
        $prev = call_user_func(array($modelTable, 'find'), array($pkcol=>cond::lt($obj->$pkcol)), 1, "$pkcol desc");
        if ($prev) {
            $prev = array_shift($prev);
            $this->view->assign("prevobjid", $prev->id);
            $prev = url($this, 'object', $class, $prev->id);
            $this->view->assign("prevobjlink", $prev);
        }

        $next = call_user_func(array($modelTable, 'find'), array($pkcol=>cond::gt($obj->$pkcol)), 1, $pkcol);
        if ($next) {
            $next = array_shift($next);
            $this->view->assign("nextobjid", $next->id);
            $next = url($this, 'object', $class, $next->id);
            $this->view->assign("nextobjlink", $next);
        }

        $this->view->assign('class', $class);
        $this->view->assign('obj', $obj);
        $this->view->assign('pkcol', $pkcol);
        $this->view->assign('pk', $pk);

        $f = array_keys($obj->dump());
        usort($f, array($this, 'sortfields'));
        $this->view->assign('fields', $f);

        $f = $obj->natural_members();
        usort($f, array($this, 'sortfields'));
        $this->view->assign('natmembers', $f);

        $f = $obj->virtual_members();
        usort($f, array($this, 'sortfields'));
        $this->view->assign('virtmembers', $f);

        $mall = get_class_methods($obj);
        $mvirt = array();
        foreach ($mall as $x) {
            if (preg_match('/^__get_(\w+)/', $x, $m)) {
                $mvirt[] = $m[1];
            }
        }
        sort($mvirt);
        $this->view->assign('genfields', $mvirt);

        $this->view->assign('path', self::$inspector_path);

        $this->viewname = "object.tpl";
    }

    public function sortfields($a, $b) {
        if ($a === $b && $a === 'id') return 0;
        if ($a === 'id') return -1;
        if ($b === 'id') return 1;

        if ($a === $b.'_id') return -1;
        if ($b === $a.'_id') return 1;

        return strcasecmp($a, $b);
    }

    static public function smarty_modifier_objlink($v) {
        if (is_object($v)) {
            if ($v instanceof Model) {
                $modelTable = eval("return ".get_class($v).'::$__table__;');
                $pk = $modelTable->pk;
                $link = new url('_datainspector', 'object', get_class($v), $v->$pk);
                $link->absolute();
                if (self::$last_fieldname) {
                    $link .= "?path=".self::encode_varpath(self::$inspector_path.'->'.self::$last_fieldname);
                } else {
                    $link .= "?path=".self::encode_varpath(self::$inspector_path);
                }
                return sprintf('<a href="%s">%s(%s=%s)</a>', $link, get_class($v), $pk, $v->$pk);
            } elseif ($v instanceof ModelCollection) {
                $c = count($v);
                $c = $c ? sprintf('(%d member%s)', $c, $c==1?'':'s') : '(empty)';
                return sprintf("collection of %s %s", $v->ofclass, $c);
            } elseif ($v instanceof form) {
                return "form ".$v->name();
            } else {
                return "--UNKNOWN OBJECT--";
            }
        }
        return "--NOT AN OBJECT--";
    }

    static private function encode_varpath($x) {
        return urlencode(str_replace('=', '', base64_encode($x)));
    }

    static public function smarty_modifier_notefieldname($v) {
        self::$last_fieldname = $v;
        return '';
    }

    static public function smarty_modifier_noteobjlink($v) {
        self::$last_objlink = $v;
        return '';
    }

    public function smarty_modifier_getimageobj($v) {
        foreach ($v->image as $i) {
            return $i;
        }
        return NULL;
    }

}

