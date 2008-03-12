<?php

class controller__media extends base_controller {
    private $tplprocess;

    public function __construct($r=array()) {
        $this->allowed_methods = array('views');
        if (lib::client_is_internal_host()) {
            $this->allowed_methods[] = 'views_demo';
        }
        parent::__construct($r);
    }

    public function views_demo() {
        $a = func_get_args();
        $this->view->template_dir = './views_demo';
        call_user_func_array(array($this, 'views'), $a);
    }

    public function views() {
        $a = func_get_args();
        $a = join('/', $a);
        $re = '@\.('.join('|', $_SERVER['allowed_dynamic_media']).')$@';
        if (!preg_match($re, $a)) {
            throw new HTTPNotFound();
        }
        $this->content_type = lib::content_type_from_extension($a);
        if (!file_exists($this->view->template_dir.'/'.$a)) {
            $a .= ".tpl";
            if (!file_exists($this->view->template_dir.'/'.$a)) {
                throw new HTTPNotFound();
            }
            $this->tplprocess = true;
        }
        $this->viewname = $a;
    }

    public function _find_related_content($ext) {
        # related content files don't have related content
        return array();
    }

    public function _render() {
        if ($this->tplprocess) {
            $this->view->left_delimiter = '{{';
            $this->view->right_delimiter = '}}';
            parent::_render();
        } else {
            header("Content-type: ".$this->content_type);
            readfile($this->view->template_dir.'/'.$this->viewname);
        }
        exit;
    }

}

