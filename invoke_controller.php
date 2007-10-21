<?php


function invoke_controller($request) {

    $controller = array_shift($request);
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

    $controller = new $controller_class($request);
    return $controller;

}

function invoke_form_handler() {
    foreach ($_POST as $k=>$a) {
        if (is_array($a)) {
            # - is illegal in function names in PHP, so safe to use as a separator here
            list($model, $formid) = split('-', $k);
            if ($formid) {
                $modelfile = sprintf('models/%s.php', $model);
                $handlefunc = sprintf('handle_form_%s', $formid);
                if (file_exists($modelfile)) {
                    require_once $modelfile;
                    if (method_exists($model, $handlefunc)) {
                        call_user_func(array(model($model), $handlefunc));
                    }
                } 
            } # no - in form name
        } # not array
    }
}


/*
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
*/
