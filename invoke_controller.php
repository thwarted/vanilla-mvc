<?php

function mk_invoke_link() {
    $a = func_get_args();
    if (count($a) == 1 && is_array($a[0])) {
        $a = $a[0];
    }

    $controller = array_shift($a);
    $method = array_shift($a);
    if (preg_match('/\W/', $controller)) {
        throw new Exception('illegal characters in controller name');
    }
    if (preg_match('/\W/', $method)) {
        throw new Exception('illegal characters in method name');
    }
    $controller_file = "controllers/$controller.php";
    $controller_class = "controller_$controller";

    if (!file_exists($controller_file)) {
        throw new HTTPNotFound("controller $controller file not found");
    }

    require_once($controller_file);

    if (!class_exists($controller_class)) {
        throw new HTTPNotFound("controller $controller code not found");
    }

    $x = preg_replace('@/+$@', '', $_SERVER['uribase']);
    $b = join('/', array($x, $controller, $method));
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

    if ($request) {
        $x = explode('/', $request);
    } else {
        if (!isset($_SERVER['default_controller'])) {
            throw new Exception("default controller not specified");
        }
        $x = $_SERVER['default_controller'];
    }

    $controller = array_shift($x);
    if (preg_match('/\W/', $controller)) {
        throw new HTTPNotFound('illegal characters in controller name');
    }
    $controller_file = "controllers/$controller.php";
    $controller_class = "controller_$controller";

    if (!file_exists($controller_file)) {
        throw new HTTPNotFound("controller $controller file not found");
    }

    require_once($controller_file);

    if (!class_exists($controller_class)) {
        throw new HTTPNotFound("controller $controller code not found");
    }

    $controller = new $controller_class();

    if (count($x)) {
        $m = array_shift($x);
        if (preg_match('/\W/', $m)) {
            throw new HTTPNotFound('illegal characters in method');
        }
        $method = false;
        foreach (array($m, "_$m") as $trym) {
            if (method_exists($controller, $trym)) {
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
        call_user_func_array(array($controller, $method), $r);
    }

    return $controller;

}

?>
