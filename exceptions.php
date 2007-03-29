<?php

class ContextException extends Exception { }

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


?>
