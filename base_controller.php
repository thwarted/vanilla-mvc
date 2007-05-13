<?php

require_once "vanilla/lib.php";

class base_controller {
    protected $viewname;
    protected $view;
    protected $autoRender;

    public function __construct($method=NULL) {
        $this->in_valid_context($method);

        $this->view = lib::smarty_factory();
        $this->autoRender = true;

    }

    protected function in_valid_context($method=NULL) {
        # you'll want to raise ContextException here
        # if, for example, this controller requires
        # a session to be logged in, and it's not
        return true;
    }

/*
    protected function plink() {
        global $baseURI;

        $x = $baseURI;

        $args = func_get_args();
        if (!count($args)) $args[] = '';
        foreach ($args as $a) {
            $x .= "/".urlencode($a);
        }
        return $x;
    }
*/

    protected function json_result($val) {
        /* should convert to use the PHP json_encode function here */
        $j = new JSON();
        $valstr = $j->encode($val);
        header("X-JSON: ".$valstr);
    }

    public function render() {
        if ($this->viewname) {
            if ($this->viewname === 'empty') {
                return '';
            }
            $viewfile = $this->viewname;
        } else {
            $v = get_class($this);
            $v = preg_replace('/^controller_/', '', $v);
            $viewfile = "$v/index.tpl";
        }
        if (!file_exists($this->view->template_dir."/".$viewfile)) {
            throw new HTTPNotFound("view \"".$viewfile."\" not found");
        }

        if ($this->autoRender) {
            $this->view->display($viewfile);
        } else {
            return $this->view->fetch($viewfile);
        }
    }

}

?>
