<?php

# this controller is only invoked to handle HTTP error codes
# it should never be invoked from a URL

class controller_errorpage extends base_controller {
    private $request;

    public function _in_valid_context($request=array()) {
        return true;
    }

    public function _invoke() {
        $this->request = $_SERVER['request'];
        # override default invocation from the constructor
        return;
    }

    public function _render($e=NULL) {
        if ($e instanceof HTTPException) {
            $this->view->assign('exception', $e);
            $errstr = get_class($e);
            $templatefile = $errstr.".tpl";
            $errstr = preg_replace('/^HTTP/', '', $errstr);
            $errstr = trim(preg_replace('/([A-Z])/', ' $1', $errstr));

            $afn = array(
                array($this->view->template_dir.'/errorpage', $templatefile),
                array($this->view->template_dir.'/errorpage', 'default.tpl'),
                array("./vanilla/extensions/errorpage/views", $templatefile),
                array("./vanilla/extensions/errorpage/views", 'default.tpl'),
            );
            foreach ($afn as $a) {
                list($tdir, $tf) = $a;
                if (file_exists("$tdir/$tf")) {
                    $this->view->template_dir = $tdir;
                    $this->viewname = $tf;
                    break;
                }
            }
        } else {
            $errstr = '';
            $this->view->template_dir = "./vanilla/extensions/errorpage/views";
            $this->viewname = "exception.tpl";
        }
        $this->view->assign('errorstr', $errstr);

        return parent::_render();
    }
}


