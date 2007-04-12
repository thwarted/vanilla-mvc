<?php

chdir('..');

foreach (array('setup', 'models', 'views', 'controllers') as $__x) {
    if (!is_dir($__x)) {
        die("vanillaMVC is not properly setup, $__x directory is missing");
    }
}

require_once "setup/global_conf.php";

require_once "vanilla/lib.php";
require_once "vanilla/exceptions.php";
require_once "vanilla/form.php";
require_once "vanilla/dbi.php";
require_once "vanilla/base_controller.php";

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


$request = preg_replace('@^'.$_SERVER['uribase'].'@', '', $_SERVER['REQUEST_URI']);
$request = preg_replace('@\?.*$@', '', $request);
$request = preg_replace('@/+@', '/', $request);
$_SERVER['urirequest'] = $_SERVER['uribase'].$request;
$request = preg_replace('@^/+@', '', preg_replace('@/+$@', '', $request));

if ($request) {
    d($request, 'request');
}

require_once "vanilla/invoke_controller.php";

try {

    $controller = invoke_controller($request);

    if (count($_POST)) { d($_POST, 'POST variables'); }
    #if (count($_SESSION)) { d($_SESSION, 'SESSION variables'); }

    $controller->render();

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
}

?>
