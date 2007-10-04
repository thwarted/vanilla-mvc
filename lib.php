<?php

$_SERVER['show_traceback_in_browser'] = false;

require_once "vanilla/smarty/Smarty.class.php";
require_once "vanilla/smarty_extensions.php";
require_once "setup/smarty_custom.php";

class lib {

    public static $debugboxshown = false;
    public static $appvars = false;

    /*
    public function staticdir($f) {
        if ($f{0} == '/') { $f = substr($f, 1); }
        return $_SERVER['SQUIB_REPORT_BASE']."/static/$f";
    }

    public function basedir($f) {
        if ($f) {
            if ($f{0} == '/') { $f = substr($f, 1); }
            return $_SERVER['SQUIB_REPORT_BASE']."/$f";
        }
        return $_SERVER['SQUIB_REPORT_BASE'];
    }

    */

    public function smarty_factory() {
        if (!isset($_SERVER['smartybase'])) {
            $_SERVER['smartybase'] = "/var/tmp/smarty-".md5($_SERVER['SCRIPT_FILENAME']);
        }
        if (! lib::$appvars ) {
            lib::$appvars = array('filebase'=>$_SERVER['filebase'],
                                  'mediabase'=>$_SERVER['mediabase'],
                                  'uribase'=>$_SERVER['uribase'],
                                  'urirequest'=>$_SERVER['urirequest']
                                 );
        }
        $smbase = $_SERVER['smartybase'];

        #@mkdir("$smbase/templates", 0777, true);
        @mkdir("$smbase/templates_c", 0777, true);
        @mkdir("$smbase/cache", 0777, true);

        $smarty = new Smarty();

        $smarty->template_dir = "./views";
        $smarty->compile_dir = "$smbase/templates_c";
        $smarty->cache_dir = "$smbase/cache";
        # we don't set config dir, we most likely won't use it initially

        $smext = array('smarty_extensions', 'smarty_custom');
        foreach ($smext as $smo) {
            $mnames = get_class_methods($smo);
            foreach ($mnames as $method) {
                if (preg_match('/^func_(\w+)$/', $method, $m)) {
                    $smarty->register_function($m[1], array($smo, $method));
                } elseif (preg_match('/^modifier_(\w+)$/', $method, $m)) {
                    $smarty->register_modifier($m[1], array($smo, $method));
                } elseif (preg_match('/^block_(\w+)$/', $method, $m)) {
                    $smarty->register_block($m[1], array($smo, $method));
                }
            }
        }
        # these are order dependent
        $smarty->register_prefilter(array('smarty_extensions', 'prefilter_convert_loop_breaks'));
        $smarty->register_prefilter(array('smarty_extensions', 'prefilter_convert_loop_continue'));

        # should be fixed to 
        $smarty->assign_by_ref('app', lib::$appvars);

        $smarty->link = array('root'=>$_SERVER['uribase']);
        $smarty->assign_by_ref('link', $smarty->link);

        if (file_exists("setup/template_conf.php")) {
            include "setup/template_conf.php";
        }

        return $smarty;
    }

    public function el($v) {
        error_log(var_export($v, true));
    }

    public function qd($v) {
        print "<pre>"; print_r($v); print "</pre>";
    }

    public function debugbox() {
        if (!$_SERVER['internal_hosts_regexp'] || !preg_match($_SERVER['internal_hosts_regexp'], $_SERVER['REMOTE_ADDR'])) {
            return '';
        }
        $box = "<div id='debugbox' style='clear: both; margin-top: 8em; padding: 4em 0.5em 0px 0.5em;'>";

        $box .= "<table width='100%' border='1' rules='rows,cols' cellpadding='4'>\n";

        $_SERVER['REQUEST_TIME_END'] = microtime(true);
        $runtime = $_SERVER['REQUEST_TIME_END'] - $_SERVER['REQUEST_TIME'];
        $box .= lib::trow('execution time', sprintf('%0.6f sec', $runtime));

        global $dbh;

        #$box .= lib::trow('database stats', $dbh->stats());

        global $__RECMSG;
        if ($__RECMSG) {
            $msg = '<dl style="padding-left: 2em; margin-left: -20px;">';
            foreach ($__RECMSG as $k) {
                $msg .= $k;
            }
            $msg .= '</dl>';
            $box .= lib::trow('messages', $msg);
        } else {
            $box .= lib::trow('messages', "(none)");
        }

        $box .= '</table></div>';

        lib::$debugboxshown = true;

        return $box;
    }

    private function trow($l, $v) {
        return "<tr><td width='7%' align='right' valign='top' style='font-family: Arial,Helvetica,sans-serif; font-size: 12px;'>$l</td><td style='font-family: Arial,Helvetica,sans-serif; font-size: 12px;' align='left'>$v</td></tr>\n";
    }

    public function internal_error($msg = '') {
        header("HTTP/1.1 500 Internal Error");
        print "<html><head><title>Internal Error</title></head><body><h1>Internal Error</h1>$msg<hr/>".$_SERVER["SERVER_SIGNATURE"]."</body></html>";
    }

    # should this be provided functionality
    public function enforce_cookie_requirement() {
        $cookiename = 'phpsessid';
        /* safari apparently doesn't send cookies on a HEAD request, 
         * so avoid doing multiple useless requests looking for cookies
         * is this standards compliant?
         */
        if ($_SERVER['REQUEST_METHOD'] === 'HEAD') return;

        # RFC2616 (HTTP/1.1) says we should be generating absolute URIs here
        if (!isset($_COOKIE[$cookiename])) {
            if (!isset($_GET['_crx'])) {
                header("HTTP/1.1 302 Moved");
                $sep = (strstr($_SERVER['REQUEST_URI'], '?')) ? '&' : '?';
                header("Location: ".$_SERVER['REQUEST_URI'].$sep."_crx=".uniqid());
                exit;
            } else {
                require "static/error-need-cookies.html";
                exit;
            }
        } else {
            if (isset($_GET['_crx'])) {
                header("HTTP/1.1 302 Moved");
                $x = $_SERVER['REQUEST_URI'];
                $x = preg_replace('/&?_crx=\w+/', '', $x);
                $x = preg_replace('/\?$/', '', $x);
                header("Location: $x");
                exit;
            }
        }
    }

    public function log_exception($e) {
        $msg = $e->getMessage();
        $code = $e->getCode();
        $msg = preg_replace("/\n/", "<br/>", $msg);
        print "<h2>".get_class($e)." (code $code)</h2><tt>$msg</tt>\n";
        if ($_SERVER['show_traceback_in_browser']) {
            print "<hr/>Traceback: <pre>";
        }
        error_log("cwd is ".getcwd());
        $x = $e->getTraceAsString();
        foreach (explode("\n", $x) as $l) {
            $l = preg_replace('@(\d) '.$_SERVER['filebase'].'(.+)@', '\1 \2', $l);
            error_log($l);
            if ($_SERVER['show_traceback_in_browser']) {
                $l = preg_replace('/(\d\)|function\]: )/', '$1<strong>', $l);
                print "$l</strong>\n";
            }
        }
        if ($_SERVER['show_traceback_in_browser']) {
            print "</pre>";
        }
    }

}

$__RECMSG = array();

function d($msg, $desc = '') {
    global $__RECMSG;

    $dbt = debug_backtrace();
    $cfunc = '';
    if (!empty($dbt[1])) {
        $cfunc = sprintf('<tt>%s%s</tt> @ ', 
                         (empty($dbt[1]['class']) ? 'root scope ' : $dbt[1]['class'].'::'), 
                         (empty($dbt[1]['function']) ? '' : $dbt[1]['function']));
    }
    $file = preg_replace('@^'.$_SERVER['filebase'].'@', '', $dbt[0]['file']);
    if ($desc) {
        $desc = sprintf('<strong>%s</strong> - ', $desc);
    }
    $caller = sprintf('%s%s<em><tt>%s:%d</tt></em>', $desc, $cfunc, $file, $dbt[0]['line']);
    if (!is_string($msg)) {
        ob_start();
        print_r($msg);
        $msg = ob_get_contents();
        ob_end_clean();
        $msg = preg_replace("/Array\n\s*\(\n\s*\)\n\n/", "Array()\n", $msg);
    }
    $msg = "<pre style=\"margin-left: -10px;\">".htmlentities($msg)."</pre>";
    $__RECMSG[] = "<dt>$caller</dt><dd style=\"margin-bottom: 10px;\">$msg</dd>";
}

set_exception_handler(array('lib', 'log_exception'));

?>
