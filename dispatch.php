<?php
/* Copyright 2005-2007 Andrew A. Bakun
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

$_SERVER['starttime'] = microtime(true);

chdir(dirname(dirname($_SERVER['SCRIPT_FILENAME'])));

foreach (array('setup', 'models', 'views', 'controllers') as $__x) {
    if (!is_dir($__x)) {
        die("vanillaMVC is not properly setup, $__x directory is missing");
    }
}

require_once "vanilla/lib.php";
require_once "vanilla/exceptions.php";
require_once "vanilla/form.php";
require_once "vanilla/dbi.php";
require_once "vanilla/base_controller.php";
require_once "vanilla/invoke_controller.php";
#require_once "vanilla/base_model.php";
require_once "vanilla/SchemaDatabase.php";

require_once "setup/global_conf.php";

$_SERVER['uribase'] = preg_replace('@/vanilla/dispatch.php$@', '', $_SERVER['SCRIPT_NAME']);
$_SERVER['uribase'] .= '/';
$_SERVER['mediabase'] = $_SERVER['uribase'].'media/';
$_SERVER['filebase'] = preg_replace('@/vanilla/dispatch.php$@', '', $_SERVER['SCRIPT_FILENAME']);

try {
    require_once "vanilla/setup.php";
} catch (Exception $e) {
    lib::log_exception($e);
    print lib::debugbox();
    exit;
}

try {

    $request = lib::parse_request();
    $_SERVER['request'] = $request;

    #if ($request) { d($request, 'request'); }

    # FIXME this really needs to be better integrated
    if (function_exists("pre_dispatch_hook")) {
        pre_dispatch_hook($request);
    }

    # FIXME perhaps the the constructor on the controller
    #       is what needs to invoke form handlers
    invoke_form_handler();

    $controller = invoke_controller($request);

    #if (count($_POST)) { d($_POST, 'POST variables'); }
    #if (count($_SESSION)) { d($_SESSION, 'SESSION variables'); }

    $controller->_render();

} catch(HTTPException $e) {

    header("HTTP/1.1 ".$e->getCode().' '.$e->getMessage());
    header("Content-type: text/html");
    if ($loc = $e->location()) {
        header("Location: $loc");
    }
    print $e->body();
    print lib::debugbox();

} catch(DataException $e) {

    header("HTTP/1.1 404 Data Exception");
    if ($j = $e->value()) {
        $json = new JSON;
        $j = $json->encode($j);
        header("X-JSON: $j");
    }
    print $e->body();

} catch(Exception $e) {

    header("HTTP/1.1 500 Internal Error");
    lib::log_exception($e);
    print lib::debugbox();

}

