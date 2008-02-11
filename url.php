<?php

# implements a url object, that when in the context of a string, prints its URL
#
#  $u = new url($path, $components, $whatever);
#  $u->secure(true);                  # turn on https
#  $u->authentication($user, $pass);
#  $u->domain($newdom);               # set the authority domain
#  $u->absolute(true);                # generate an absolute URL
#  $u['get'] = 'variable';            # set GET variables
#  $u->merge(array(key=>value));      # merge the passed array into the get variables
#
#  strval($u)                         # return as a string
#    or use in a string context

class url implements Countable, ArrayAccess, Iterator {
    private $getvars = array();
    private $path = array();
    private $absolute = false;
    private $domain = false;
    private $secure = false;
    private $includeprotocol = false;
    private $authuser;
    private $authpass;

    public function __construct() {
        $p = func_get_args();
        $this->path($p);
        # FIXME verify this CGI var, only sure for Apache
        $this->secure = isset($_SERVER['HTTPS']);
        $this->domain = $_SERVER['SERVER_NAME'];
    }

    public function path() {
        $p = array_values_recursive(func_get_args());
        $np = array();
        foreach ($p as $pc) {
            if (empty($pc)) {
                continue;
            } elseif (is_object($pc)) {
                $pc = get_class($pc);
                if (preg_match('/^controller_(\w+)$/', $pc, $m)) {
                    $pc = $m[1];
                } else {
                    throw new Exception("$pc does not appear to be a controller class name");
                }
            } elseif (is_string($pc) && preg_match('/^controller_(\w+)$/', $pc, $m)) {
                # could pass in the result of get_class or __CLASS__;
                # in that case, strip off the prefixing controller_ part
                $pc = $m[1];
            } elseif (!(strpos($pc, '/') === false)) {
                $pc = trim($pc, " \t\n\r\x0B/\0");
                $pc = explode('/', $pc);
                while(count($pc) > 1) {
                    array_push($np, array_shift($pc));
                }
                if ($pc) $pc = array_shift($pc);
            }
            $np[] = trim($pc, " \t\n\r\x0B/\0");
        }
        if (count($np) == 1) {
            $np[] = '';
        }   
        $this->path = $np;
    }

    public function absolute($v = true) {
        $this->absolute = !(!$v);
    }

    public function authentication($user, $pass) {
        $this->authuser = strval($user);
        $this->authpass = strval($pass);
    }

    public function domain($d) {
        $this->domain = strval($d);
    }

    public function secure($s = true) {
        $this->absolute = true;
        $this->secure = !(!$s);
    }
    
    public function merge($a) {
        foreach ($a as $k=>$v) {
            $this[$k] = $v;
        }
    }

    public function __toString() {
        if ($this->authuser && $this->authpass) {
            $this->absolute = true;
        }

        if (is_array($_SERVER['default_controller']) && $this->path === $_SERVER['default_controller']) {
            $v = $_SERVER['uribase'];
        } else {
            $o = array_values_recursive(array(rtrim($_SERVER['uribase'], '/'), $this->path));
            $v = join('/', $o);
            # if we are generating an empty path (to the root of the site)
            # then set it to the base uri
            if (!$v) {
                $v = $_SERVER['uribase'];
            }
            $v = preg_replace('@/\./@', '/', $v);
        }
        if ($this->absolute) {
            $v = $this->make_absolute($v);
        }
        if ($this->authuser && $this->authpass) {
            $v = $this->add_auth($v);
        }
        if ($this->getvars) {
            $v .= '?'.http_build_query($this->getvars);
        }
        return $v;
    }

    private function make_absolute($u) {
        if (!$u) { return $u; }
        if (preg_match('@^https?://@', $u)) {
            return $u;
        }
        if (!preg_match('@^/@', $u)) {
            $u = "/$u";
        }

        $secure = $this->secure ? 's' : '';
        $u = sprintf('http%s://%s%s', $secure, $this->domain, $u);
        return $u;
    }

    private function add_auth($u) {
        return preg_replace('-^((https?:)?//)(\w)-', '$1'.urlencode($this->authuser).':'.urlencode($this->authpass).'@$3', $u);
    }

    # Countable interface
    public function count() {
        return count($this->getvars);
    }

    # ArrayAccess interface
    public function offsetExists($offset) {
        return (isset($this->getvars[$offset]) && is_object($this->getvars[$offset]));
    }

    public function offsetGet($offset) {
        if (!isset($this->getvars[$offset])) {
            return NULL;
        }
        return $this->getvars[$offset];
    }

    public function offsetSet($offset, $value) {
        if (!isset($offset)) {
            $this->path[] = $value;
        } else {
            $this->getvars[$offset] = $value;
        }
    }

    public function offsetUnset($offset) {
        unset($this->getvars[$offset]);
    }

    # Iterator interface
    public function current() { return current($this->getvars); }
    public function rewind() { return reset($this->getvars); }
    public function key() { return key($this->getvars); }
    public function next() { return next($this->getvars); }
    public function valid() { return current($this->getvars) ? true : false; }

}

