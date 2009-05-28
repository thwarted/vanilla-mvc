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

$_SERVER['starttime'] = microtime(true);
$_SERVER['routes'] = array();

chdir(dirname(dirname($_SERVER['SCRIPT_FILENAME'])));

foreach (array('setup', 'models', 'views', 'controllers') as $__x) {
    if (!is_dir($__x)) {
        die("vanillaMVC is not properly setup, $__x directory is missing");
    }
}

require_once "vanilla/lib.php";
require_once "vanilla/url.php";
require_once "vanilla/exceptions.php";
require_once "vanilla/form.php";
require_once "vanilla/dbi.php";
require_once "vanilla/base_controller.php";

require_once "setup/global_conf.php";

$_SERVER['uribase'] = preg_replace('@/vanilla/dispatch.php$@', '', $_SERVER['SCRIPT_NAME']);
$_SERVER['uribase'] .= '/';
$_SERVER['mediabase'] = $_SERVER['uribase'].'media/';
$_SERVER['filebase'] = preg_replace('@/vanilla/dispatch.php$@', '', $_SERVER['SCRIPT_FILENAME']);

require_once "vanilla/dispatchcore.php";

try {
    require_once "vanilla/setup.php";
} catch (Exception $e) {
    lib::log_exception($e);
    print lib::debugbox();
    exit;
}

# if the application's setup code doesn't define a class named Dispatch,
# create one here which is just empty
if (!class_exists('Dispatch')) { class Dispatch extends AbstractDispatch { } }

try {

    $dispatch = new Dispatch();
    $dispatch->parse_request();
    $dispatch->invoke_form_handler();
    $dispatch->find_controller_class();
    $dispatch->create_controller();
    $dispatch->render();

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

