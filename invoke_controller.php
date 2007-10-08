<?php

function mk_invoke_link() {
    $a = func_get_args();
    if (count($a) == 1 && is_array($a[0])) {
        $a = $a[0];
    }

    $controller = array_shift($a);
    if (preg_match('/\W/', $controller)) {
        throw new Exception('illegal characters in controller name');
    }
    $controller_file = "controllers/$controller.php";
    $controller_class = "controller_$controller";

    if (!file_exists($controller_file)) {
        error_log("creating link to non-existent controller file \"$controller\"");
        #throw new HTTPNotFound("controller $controller: file not found");
    } else {
        require_once($controller_file);
    }


    if (!class_exists($controller_class)) {
        error_log("creating link to non-existent controller class \"$controller\"");
        #throw new HTTPNotFound("controller $controller: code not found");
    }

    $path = preg_replace('@/+$@', '', $_SERVER['uribase']);
    $path = array($path, $controller);

    if ($a) {
        $method = array_shift($a);
        if ($method && preg_match('/\W/', $method)) {
            throw new Exception('illegal characters in method name');
        }
        $path[] = $method;
    }

    $b = join('/', $path);
    $r = array();
    $o = array();
    foreach ($a as $k=>$v) {
        if (is_numeric($k)) {
            $r[] = urlencode($v);
        } else {
            $o[$k] = $v;
        }
    }
    $b .= (count($r) ? '/' : '').join('/', $r);
    if ($o) {
        $b .= '?'.http_build_query($o);
    }

    return $b;
}

function invoke_controller($request) {

    $x = false;
    if ($request) {
        $x = explode('/', $request);
    }

    if (!isset($_SERVER['default_controller'])) {
        throw new Exception("default controller not specified");
    }
    if (!$x) {
        $x = $_SERVER['default_controller'];
    } elseif (count($x) < 2) {
        $x[] = '_default';
        #throw new HTTPException('Moved', 302, mk_invoke_link($_SERVER['default_controller'][0], $_SERVER['default_controller'][1]));
    }

    $controller = array_shift($x);
    if (preg_match('/\W/', $controller)) {
        throw new HTTPNotFound('illegal characters in controller name (1)');
    }
    $controller_file = "controllers/$controller.php";
    if ($controller === '_media') {
        $controller_file = "vanilla/dynamic_media.php";
        $controller_class = "dynamic_media_controller";
    } else {
        if (preg_match('/^_/', $controller)) {
            throw new HTTPNotFound('illegal characters in controller name (2)');
        }
        $controller_class = "controller_$controller";
    
        if (!file_exists($controller_file)) {
            throw new HTTPNotFound("controller $controller: file not found");
        }
    }

    require_once($controller_file);

    if (!class_exists($controller_class)) {
        throw new HTTPNotFound("controller $controller: code not found");
    }

    if (count($x)) {
        $m = array_shift($x);
        if (preg_match('/\W/', $m)) {
            throw new HTTPNotFound('illegal characters in method');
        }
        $method = false;
        foreach (array($m, "_$m") as $trym) {
            if (method_exists($controller_class, $trym)) {
                $method = $trym;
                break;
            }
        }
        if (!$method) {
            throw new HTTPNotFound($controller_class."::".$m." not found");
        }
        $r = array();
        foreach ($x as $v) {
            if (preg_match('/^(\w+)=(.*)$/', $v, $m)) {
                $_REQUEST[$m[1]] = urldecode($m[2]);
            } else {
                $r[] = urldecode($v);
            }
        }
        $controller = new $controller_class($m);
        call_user_func_array(array($controller, $method), $r);
    }

    return $controller;

}


