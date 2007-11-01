<?php

require_once "vanilla/lib.php";

abstract class base_controller {
    protected $viewname;
    protected $view;
    protected $autoRender;
    protected $extraargs = array();
    // if allowed_methods is true, ::_invoke will allow
    // any method to be called 
    // set to an array of allowed method names otherwise
    protected $allowed_methods = true;

    public function __construct($request) {
        $this->_in_valid_context($request);

        $this->view = lib::smarty_factory();
        $this->autoRender = true;

        $this->_invoke($request);
    }

    protected function _invoke($req = array()) {
        if (!$req || !is_array($req)) {
            $req = array('index'); # default method name
        }
        $m = array_shift($req);
        if (preg_match('/\W/', $m)) {
            throw new HTTPNotFound('illegal characters in method');
        }
        if (preg_match('/^_/', $m)) {
            # FIXME could throw HTTPNotFound here, to avoid 
            # leaking information about the implementation
            throw new HTTPUnauthorized();
        }
        $method = false;
        foreach (array($m, $m.'_') as $trym) {
            if (method_exists($this, $trym)) {
                $method = $trym;
                break;
            }
        }
        if (is_array($this->allowed_methods)) {
            if (!in_array($method, $this->allowed_methods)) {
                throw new HTTPUnauthorized();
            }
        } elseif (! $this->allowed_methods ) {
            throw new HTTPUnauthorized();
        }
        if (!$method) {
            throw new HTTPNotFound(get_class($this).'::'.$method.' not found');
        }
        $r = array();
        foreach ($req as $v) {
            if (preg_match('/^(\w+)=(.*)$/', $v, $m)) {
                $this->extraargs[$m[1]] = urldecode($m[2]);
            } else {
                $r[] = trim(urldecode($v));
            }
        }
        # all protected and public methods are accessible
        call_user_func_array(array($this, $method), $r);
    }

    protected function _in_valid_context($request=array()) {
        # you'll want to raise ContextException here
        # if, for example, this controller requires
        # a session to be logged in, and it's not
        return true;
    }

    protected function _json_result($val) {
        /* should convert to use the PHP json_encode function here */
        $valstr = json_encode($val);
        header("X-JSON: ".$valstr);
    }

    private function _get_metadata($f) {
        $metadata = array();
        $fh = fopen($f, 'r');
        if (!$fh) {
            return $metadata;
        }
        $maxlines = 10;
        while($maxlines > 0 && ($line = fgets($fh))) {
            if (preg_match('/\*\//', $line)) {
                break;
            }
            if (preg_match('/\* (\w+): (.+)$/', $line, $m)) {
                list($junk, $k, $v) = $m;
                $metadata[$k] = trim($v);
            }
            $maxlines--;
        }
        fclose($fh);
        if (preg_match('/\.css$/', $f)) {
            if (!isset($metadata['relation'])) {
                $metadata['relation'] = 'stylesheet';
            }
        }
        return $metadata;
    }

    public function _render() {
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

