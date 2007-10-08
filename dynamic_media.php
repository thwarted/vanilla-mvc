<?php

class dynamic_media_controller extends base_controller {
    private $mediafile;

    # note that this method name matches the name of the views/ directory
    public function views() {
        $a = func_get_args();
        $a = join('/', $a);
        $this->mediafile = $this->view->template_dir."/".$a;
    }

    public function _render() {
        if (!$this->mediafile || !file_exists($this->mediafile)) {
            throw new HTTPNotFound();
        }
        # FIXME what should we do here if allowed_dynamic_media isn't set?
        foreach ($_SERVER['allowed_dynamic_media'] as $ext) {
            if (preg_match('/\.'.$ext.'$/', $this->mediafile)) {
                header("Content-type: ".lib::content_type_from_extension($this->mediafile));
                readfile($this->mediafile);
                exit;
            }
        }
        throw new HTTPNotFound();
    }

}

