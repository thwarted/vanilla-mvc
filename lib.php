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

$_SERVER['show_traceback_in_browser'] = true;

require_once "vanilla/smarty/Smarty.class.php";
require_once "vanilla/smarty_extensions.php";
require_once "setup/smarty_custom.php";

class lib {

    public static $debugboxshown = false;
    public static $appvars = false;

    static public function parse_request() {
        $request = preg_replace('@^'.$_SERVER['uribase'].'@', '', urldecode($_SERVER['REQUEST_URI']));
        $request = preg_replace('@\?.*$@', '', $request);
        $request = preg_replace('@/+@', '/', $request);
        $_SERVER['urirequest'] = $_SERVER['uribase'].$request;
        $request = trim($request, '/');
        if ($request) {
            $request = explode('/', urldecode($request));
        } else {
            if (!isset($_SERVER['default_controller'])) {
                throw new Exception("default controller not specified");
            }
            $request = $_SERVER['default_controller'];
        }

        return $request;
    }

    static public function smarty_factory() {
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

        $smarty->assign_by_ref('app', lib::$appvars);

        if (file_exists("setup/template_conf.php")) {
            include "setup/template_conf.php";
        }

        return $smarty;
    }

    static public function el($v) {
        static $first = true;
        if ($first) {
            error_log($_SERVER['starttime'].":------------------------------------------------------");
            $first = false;
        }
        error_log($_SERVER['starttime'].":".preg_replace('/\n/', '', var_export($v, true)));
    }

    static public function qd($v) {
        print "<pre>"; print_r($v); print "</pre>";
    }

    static public function client_is_internal_host() {
        if (!isset($_SERVER['SERVER_SOFTWARE'])) {
            return true; # run from the command line
        }
        if (!$_SERVER['internal_hosts_regexp'] || !preg_match($_SERVER['internal_hosts_regexp'], $_SERVER['REMOTE_ADDR'])) {
            return false;
        }
        return true;
    }

    static public function dbstats() {
        $x = array();
        foreach (DBI::$statement_types as $stmt=>$count) { 
            $x[] = sprintf('%d %s', $count, $stmt);
        }
        return sprintf('%d total queries; %d rows fetched; %0.6f query execution time; %s', 
                    DBI::$query_count, DBI::$fetchrow_count, DBI::$query_runtime, join(', ', $x));
    }

    static public function runtime() {
        $runtime = microtime(true) - $_SERVER['starttime'];
        return sprintf('%0.6f sec', $runtime);
    }

    static public function debugbox() {
        if (!lib::client_is_internal_host()) {
            return '';
        }
        if (lib::$debugboxshown) {
            return '';
        }
        $box = "<div id='debugbox' style='clear: both; margin-top: 8em; margin-bottom: 2em; padding: 4em 0.5em 0px 0.5em;'>";

        $box .= "<table width='100%' border='1' rules='all' cellpadding='4'>\n";

        $_SERVER['runtime'] = lib::runtime();
        $box .= lib::trow('execution time', $_SERVER['runtime']);
        $dbstats = lib::dbstats();
        $box .= lib::trow('database', $dbstats);

        ob_start(); print_r($_SESSION); $msg = ob_get_contents(); ob_end_clean();
        $msg = preg_replace('/(^Array\n\(|\n\)$)/', '', $msg);
        $box .= lib::trow('session', "<pre style='margin: 0px; padding: 0px;'>$msg</pre>");

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

    public function log_callstack() {
        try { throw new Exception(''); } catch (Exception $e) {
            $x = $e->getTraceAsString();
            $x = preg_split('/\n/', $x);
            error_log("---------------------");
            foreach ($x as $l) {
                error_log($l);
            }
            error_log("---------------------");
        }
    }

    private function trow($l, $v) {
        return "<tr><td width='7%' align='right' valign='top' style='font-family: Arial,Helvetica,sans-serif; font-size: 12px;'>$l</td><td style='font-family: Arial,Helvetica,sans-serif; font-size: 12px;' align='left'>$v</td></tr>\n";
    }

    public function internal_error($msg = '') {
        header("HTTP/1.1 500 Internal Error");
        print "<html><head><title>Internal Error</title></head><body><h1>Internal Error</h1>$msg<hr/>".$_SERVER["SERVER_SIGNATURE"]."</body></html>";
    }

    # FIXME should this be provided functionality?
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

    static public function log_exception($e) {
        $msg = $e->getMessage();
        $code = $e->getCode();
        $html = isset($_SERVER['SERVER_SOFTWARE']);
        if (method_exists($e, "getStatement")) {
            $msg .= ($html?"<br/>":"\n").$e->getStatement();
        }
        error_log($msg);
        if ($html) {
            $msg = preg_replace("/\n/", "<br />", $msg);
            print "<h2>".get_class($e)." (code $code)</h2><tt>$msg</tt>\n";
        } else {
            print get_class($e)." (code $code)\n$msg\n";
        }
        $showtb = $html && ($_SERVER['show_traceback_in_browser'] && lib::client_is_internal_host());
        if ($showtb) {
            if ($html) {
                print "<hr/>Traceback: <pre>";
            }
        }
        error_log("cwd is ".getcwd());
        # FIXME should use the getTrace() method instead for easier/more-accurate formatting
        $x = $e->getTraceAsString();
        foreach (explode("\n", $x) as $l) {
            $l = preg_replace('@(\d) '.$_SERVER['filebase'].'/(.+)@', '\1 \2', $l);
            error_log($l);
            if ($showtb) {
                print "$l\n";
            }
        }
        if ($showtb) {
            print "</pre>";
        }
    }

    static public function content_type_from_extension($filename) {
        if (preg_match('/\.js$/', $filename)) {
            return "text/javascript";
        }
        if (preg_match('/\.css$/', $filename)) {
            return "text/css";
        }
        return "text/plain";
    }

}

function url() {
    $o = array(rtrim($_SERVER['uribase'], '/'));
    $c = array_values_recursive(func_get_args());
    if (is_array($_SERVER['default_controller']) && $c === $_SERVER['default_controller']) {
        return $_SERVER['uribase'];
    }
    foreach ($c as $i) {
        if (is_object($i)) {
            $i = get_class($i);
            if (preg_match('/^controller_(\w+)$/', $i, $m)) {
                $i = $m[1];
            } else {
                throw new Exception("$i does not appear to be a controller");
            }
        } elseif (is_string($i) && preg_match('/^controller_(\w+)$/', $i, $m)) {
            # could pass in the result of get_class or __CLASS__;
            # in that case, strip off the prefixing controller_ part
            $i = $m[1];
        } elseif (empty($i)) {
            continue;
        }
        $o[] = trim($i, '/');
    }
    if (count($o) == 1) {
        $o[] = '';
    }
    $o = join('/', $o);
    # if we are generating an empty path (to the root of the site)
    # then set it to the base uri
    if (!$o) {
        $o = $_SERVER['uribase'];
    }
    $o = preg_replace('@/\./@', '/', $o);
    return $o;
}

function absolute($u) {
    if (!$u) { return $u; }
    if (preg_match('@^https?://@', $u)) {
        return $u;
    }
    if (!preg_match('@^/@', $u)) {
        $u = "/$u";
    }
    $secure = isset($_SERVER['HTTPS']) ? 's' : ''; # FIXME verify this CGI var
    $u = sprintf('http%s://%s%s', $secure, $_SERVER['SERVER_NAME'], $u);
    return $u;
}

function array_values_recursive($a) {
    if (!is_array($a)) {
        return array($a);
    }
    $flat = array();
    foreach ($a as $value) {
        if (is_array($value)) {
            $flat = array_merge($flat, array_values_recursive($value));
        } else {
            $flat[] = $value;
        }
    }
    return $flat;
}

$__RECMSG = array();

function caller($asstring=true) {
    $tb = debug_backtrace();
    $frame = $tb[1];
    if ($asstring) {
        return sprintf('%s:%d', preg_replace('@^'.$_SERVER['filebase'].'@', '', $frame['file']), $frame['line']);
    } 
    return $tb[2];
}

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

