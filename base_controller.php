<?php

require_once "vanilla/lib.php";

class base_controller {
    protected $viewname;
    protected $view;
    protected $autoRender;
    protected $stylesheets = array();
    protected $javascripts = array();

    public function __construct($targetmethod=NULL) {
        $this->_in_valid_context($targetmethod);

        $this->view = lib::smarty_factory();
        $this->autoRender = true;

    }

    protected function _in_valid_context($targetmethod=NULL) {
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
                    $r[] = $ri;
                }
            }
        }
        foreach ($this->__related_content_searchpaths() as $p) {
            $g = "$p/$b*$ext";
            $files = glob($g);
            foreach ($files as $f) {
                $ri = $this->__build_media_info($f);
                $r[] = $ri;
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

        if ($this->autoRender) {
            $this->view->display($viewfile);
        } else {
            return $this->view->fetch($viewfile);
        }
    }

}

