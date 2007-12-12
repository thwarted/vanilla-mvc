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

require_once "vanilla/lib.php";

abstract class base_controller {
    protected $content_type = 'text/html';
    protected $viewname;
    protected $view;
    protected $autoRender;
    protected $extraargs = array();
    // if allowed_methods is true, ::_invoke will allow
    // any method to be called 
    // set to an array of allowed method names otherwise
    protected $allowed_methods = true;

    public function __construct($request) {
        $this->_in_valid_context($request);

        $this->view = lib::smarty_factory();
        $this->autoRender = true;

        $this->_invoke($request);
    }

    protected function _invoke($req = array()) {
        if (!$req || !is_array($req)) {
            $req = array('index'); # default method name
        }
        $findmethod = array_shift($req);
        if (preg_match('/^_/', $findmethod)) {
            # FIXME could throw HTTPNotFound here, to avoid 
            # leaking information about the implementation
            throw new HTTPUnauthorized();
        }
        $method = false;
        foreach (array($findmethod, $findmethod.'_', 'default_') as $trym) {
            if (preg_match('/\W/', $trym)) {
                # illegal characters are okay, since they may be handled by default_
                # we won't find a method actually named will illegal names anyway
                # since identifiers can not contain anything other 
                # than \w (unverified in PHP)
                # used to throw HTTPNotFound('illegal characters in method')
                # before we even got to this loop before
                continue;
            }
            if (method_exists($this, $trym)) {
                $method = $trym;
                break;
            }
        }
        if (is_array($this->allowed_methods)) {
            if (!in_array($method, $this->allowed_methods)) {
                throw new HTTPUnauthorized('niam');
            }
        } elseif (! $this->allowed_methods ) {
            throw new HTTPUnauthorized('amif');
        }
        if (!$method) {
            throw new HTTPNotFound(get_class($this).'::'.$method.' not found');
        }
        $r = array();
        foreach ($req as $v) {
            if (preg_match('/^(\w+)=(.*)$/', $v, $m)) {
                $this->extraargs[$m[1]] = $m[2];
            } else {
                $r[] = trim($v);
            }
        }
        # all protected and public methods are accessible
        if ($method === 'default_') {
            # NOTE if default_ is called, the method-name argument is not consumed
            array_unshift($r, $findmethod);
        }
        call_user_func_array(array($this, $method), $r);
    }

    protected function _in_valid_context($request=array()) {
        # you'll want to raise ContextException here
        # if, for example, this controller requires
        # a session to be logged in, and it's not
        return true;
    }

    protected function _json_result($val) {
        /* should convert to use the PHP json_encode function here */
        $valstr = json_encode($val);
        header("X-JSON: ".$valstr);
    }

    private function _get_metadata($f) {
        $metadata = array();
        $fh = fopen($f, 'r');
        if (!$fh) {
            return $metadata;
        }
        $maxlines = 10;
        while($maxlines > 0 && ($line = fgets($fh))) {
            if (preg_match('/\*\//', $line)) {
                break;
            }
            if (preg_match('/\* (\w+): (.+)$/', $line, $m)) {
                list($junk, $k, $v) = $m;
                $metadata[$k] = trim($v);
            }
            $maxlines--;
        }
        fclose($fh);
        if (preg_match('/\.css$/', $f)) {
            if (!isset($metadata['relation'])) {
                $metadata['relation'] = 'stylesheet';
            }
        }
        return $metadata;
    }

    public function _render() {
        if ($this->viewname) {
            if ($this->viewname === 'empty') {
                return '';
            }
            $viewfile = $this->viewname;
        } else {
            $v = get_class($this);
            $v = preg_replace('/^controller_/', '', $v);
            $viewfile = "$v/index.tpl";
        }
        if (!file_exists($this->view->template_dir."/".$viewfile)) {
            throw new HTTPNotFound("view \"".$viewfile."\" not found");
        }

        if ($this->autoRender) {
            $content_type = $this->content_type;
            if (preg_match('@^text/@', $content_type) && !preg_match('/charset=/i', $content_type)) {
                $content_type .= '; charset="UTF-8"';
            }
            header("Content-type: ".$content_type);
            if (empty($_SERVER['buffer_rendering'])) {
                header("X-DB-Stats: ".lib::dbstats());
                header("X-Runtime: ".lib::runtime());
                $this->view->display($viewfile);
            } else {
                ob_start();
                $this->view->display($viewfile);
                header("X-DB-Stats: ".lib::dbstats());
                header("X-Runtime: ".lib::runtime());
                ob_end_flush();
            }
        } else {
            return $this->view->fetch($viewfile);
        }
    }

}

