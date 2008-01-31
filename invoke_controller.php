<?php
/* Copyright 2005-2008 Andrew A. Bakun
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

function invoke_controller($request) {

    $controller = array_shift($request);
    if (preg_match('/\.(html?|txt)$/', $controller) && !$request) {
        $controller = preg_replace('/\./', '_', $controller);
    }
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
                    $cb = array($model, $handlefunc);
                    if (is_callable($cb)) {
                        call_user_func($cb);
                    }
                } 
            } # no - in form name
        } # not array
    }
}

