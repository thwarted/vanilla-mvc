<?php

abstract class AbstractDispatch {
    protected $controller_class;
    protected $controller;
    protected $request;

    public function __construct() {
        return;
    }


    public function parse_request() {
        $request = preg_replace('@^'.$_SERVER['uribase'].'@', '', urldecode($_SERVER['REQUEST_URI']));
        $request = preg_replace('@\?.*$@', '', $request);
        $request = preg_replace('@/+@', '/', $request);
        $_SERVER['urirequest'] = $_SERVER['uribase'].$request;
        $request = trim($request, '/');
        if ($request) {
            $request = explode('/', $request);
        } else {
            if (!isset($_SERVER['default_controller'])) {
                throw new Exception("default controller not specified");
            }
            $request = $_SERVER['default_controller'];
        }

        $_SERVER['request'] = $request;

        $this->pre_dispatch_hook($request);

        return $request;
    }


    private function pre_dispatch_hook($request) {
        # this is deprecated, if a pre-dispatch-hook is needed
        # the other methods should be overridden on the Dispatch
        # subclass
        if (function_exists("pre_dispatch_hook")) {
            pre_dispatch_hook($request);
        }
    }


    public function invoke_form_handler() {
        foreach ($_POST as $k=>$a) {
            if (is_array($a) && strpos($k, '-') !== false) {
                # - is illegal in function names in PHP, so safe to use as a separator here
                list($model, $formid) = explode('-', $k);
                if ($formid) {
                    $modelfile = $_SERVER['modelfiles'][$model];
                    $handlefunc = sprintf('handle_form_%s', $formid);
                    if (file_exists($modelfile)) {
                        require_once $modelfile;
                        $cb = array($model, $handlefunc);
                        if (is_callable($cb)) {
                            call_user_func($cb);
                        }
                    }
                } # no - in form name
            } # not array
        }
    }

    function find_controller_class() {
        $request = $_SERVER['request'];
        $controller = array_shift($request);
        $controller_file = $controller_class = false;

        if (isset($_SERVER['routes'])) {
            if (isset($_SERVER['routes'][$controller])) {
                $action = $_SERVER['routes'][$controller];
                $controller_class = $action['class'];
                $controller_file = $action['file'];
            }
        }

        if (!($controller_file && $controller_class)) {
            if (preg_match('/\.(html?|txt)$/', $controller) && !$request) {
                $controller = preg_replace('/\./', '_', $controller);
            }
            if (preg_match('/\W/', $controller)) {
                throw new HTTPNotFound('illegal characters in controller name (1)');
            }
            $controller_file = "controllers/$controller.php";
            $controller_class = "controller_$controller";
        }

        if (!file_exists($controller_file)) {
            throw new HTTPNotFound("controller $controller: file not found");
        }

        require_once($controller_file);

        if (!class_exists($controller_class)) {
            throw new HTTPNotFound("controller $controller: code not found");
        }

        $this->controller_class = $controller_class;
        $this->request = $request;
        return $controller_class;
    }

    function create_controller() {
        $cc = $this->controller_class;
        $this->controller = new $cc($this->request);
        return $this->controller;
    }


    function render() {
        $this->controller->_render();

        $this->post_render_hook();
    }

    private function post_render_hook() {
        # this is deprecated, if a post-render-hook is needed
        # the other methods should be overridden on the Dispatch
        # subclass
        if (function_exists('post_render_hook')) {
            post_render_hook($_SERVER['request']);
        }
    }
}
