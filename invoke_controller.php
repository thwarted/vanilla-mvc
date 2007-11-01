<?php


function invoke_controller($request) {

    $controller = array_shift($request);
    if (preg_match('/\W/', $controller)) {
        throw new HTTPNotFound('illegal characters in controller name (1)');
    }
    $controller_file = "controllers/$controller.php";
    $controller_class = "controller_$controller";
 
    if (!file_exists($controller_file)) {
        throw new HTTPNotFound("controller $controller: file not found");
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
        if (is_array($a) && strpos($k, '-') !== false) {
            # - is illegal in function names in PHP, so safe to use as a separator here
            list($model, $formid) = explode('-', $k);
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

