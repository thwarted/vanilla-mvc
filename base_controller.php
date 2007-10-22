<?php

require_once "vanilla/lib.php";

abstract class base_controller {
    protected $viewname;
    protected $view;
    protected $autoRender;
    protected $stylesheets = array();
    protected $javascripts = array();
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
                $_REQUEST[$m[1]] = urldecode($m[2]);
            } else {
                $r[] = urldecode($v);
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

    protected function _json_result($val) {
        /* should convert to use the PHP json_encode function here */
        $j = new JSON();
        $valstr = $j->encode($val);
        header("X-JSON: ".$valstr);
    }

    private function __get_metadata($f) {
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
        return $metadata;
    }

    # should this be moved to a view object? we're using smarty
    # which is the "view object", which is difficult to override
    private function __find_related_content($ext) {
        $vn = preg_replace('/\..+$/', '', $this->viewname);
        $b = basename($vn, ".$ext");
        $r = array();
        if (is_array($_SERVER['all_views_media_paths'])) {
            foreach ($_SERVER['all_views_media_paths'] as $p) {
                $g = "$p/*$ext";
                $files = glob($g);
                foreach ($files as $f) {
                    $ri = $this->__build_media_info($f);
                    $r[$f] = $ri;
                }
            }
        }
        foreach ($this->__related_content_searchpaths() as $p) {
            # order, and thus priority, of individual CSS files is undefined
            # rules should not overlap though
            # FIXME ideally, we'd want $b*$ext to appear AFTER the rest
            # since they would presumablly contain definitions we want to override
            # in the general cases
            # need a custom sort function for that, methinks.  Leaving it with the
            # loop for now to keep it obvious how things could work
            foreach (array("$p/$b*$ext", "$p/*$ext") as $g) {
                $files = glob($g);
                foreach ($files as $f) {
                    if (!isset($r[$f])) {
                        $ri = $this->__build_media_info($f);
                        $r[$f] = $ri;
                    }
                }
            }
        }
        return $r;
    }
    
    private function __build_media_info($file) {
        $ri = $this->__get_metadata($file);
        # if it's in the apache served static files directory...
        if (preg_match('/^media/', $file)) {
            # ...provide a URL directly to it
            $ri['url'] = url($file);
        } else {
            # ...otherwise serve it through the built-in dynamic
            # media controller
            $ri['url'] = url('_media', $file);
        }
        return $ri;
    }

    private function __related_content_searchpaths() {
        $x = array();
        # the order here determines the order in which they are included
        # so more specific media files should be stored in the same
        # directory as the view
        $x[] = 'media/'.dirname($this->viewname);
        $x[] = $this->view->template_dir.'/'.dirname($this->viewname);
        return $x;
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

        $this->stylesheets = array_merge($this->stylesheets, $this->__find_related_content('.css'));
        $this->javascripts = array_merge($this->javascripts, $this->__find_related_content('.js'));

        $this->view->assign('stylesheets', $this->stylesheets);
        $this->view->assign('javascripts', $this->javascripts);

        $this->view->assign('link', $_SERVER['link']);

        if ($this->autoRender) {
            $this->view->display($viewfile);
        } else {
            return $this->view->fetch($viewfile);
        }
    }

}

