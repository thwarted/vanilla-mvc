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

class ContextException extends Exception { }
class NotControllerException extends Exception { }

class DataException extends Exception {
    private $_errormsg = '';
    private $_value = NULL;

    public function __construct($em, $value = NULL) {
        $this->_errormsg = $em;
        $this->_value = $value;
        parent::__construct($em);
    }

    public function value() {
        return array('message'=>$this->_errormsg, 'value'=>$this->_value);
    }

    public function body() {
        return $this->_errormsg;
    }
}

class HTTPException extends Exception {
    private $__location;

    public function __construct($mesg, $code = 500, $location = NULL) {
        parent::__construct($mesg, $code);
        $this->__location = $location;
    }

    public function location() {
        return $this->__location;
    }

    public function body() {
        $code = $this->getCode();
        $msg = $this->getMessage();
        $ret = "<html><head><title>$msg</title></head><body><h1>$code</h1>$msg<hr/><em>".$_SERVER['SERVER_SOFTWARE']."</em></body></html>";
        return $ret;
    }
}

class HTTPMovedPermanently extends HTTPException {
    public function __construct($loc) {
        parent::__construct("found", 301, absolute($loc));
    }
}

class HTTPFoundRedirect extends HTTPException {
    public function __construct($loc) {
        parent::__construct("found", 302, absolute($loc));
    }
}

class HTTPSeeOther extends HTTPException {
    public function __construct($loc) {
        parent::__construct("see other", 303, absolute($loc));
    }
}

class HTTPUnauthorized extends HTTPException {
    public function __construct($extramsg = '') {
        $msg = 'Unauthorized';
        if ($extramsg) $msg .= " ($extramsg)";
        parent::__construct($msg, 401);
    }
}

class HTTPForbidden extends HTTPException {
    public function __construct($extramsg = '') {
        parent::__construct('Forbidden', 403);
    }
}

class HTTPNotFound extends HTTPException {
    public function __construct($extramsg = '') {
        $x = $_SERVER['REQUEST_URI'];
        $x = preg_replace('/[<>]/', '', $x);
        $x = preg_replace('/\?.*/', '', $x);
        $msg = "$x not found";
        if ($extramsg) $msg .= " ($extramsg)";
        parent::__construct($msg, 404);
    }
}


