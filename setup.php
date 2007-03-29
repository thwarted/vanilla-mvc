<?php

# we need this to execute in the global scope so that
# anything that the setup files do is global

# the documentation for the Dir object doesn't say what happens
# when the specified directory doesn't exist, so we'll use the
# functional interface

if ($__x = opendir("./setup")) {
    $__y = array();
    while (($__f = readdir($__x)) !== false) {
        if (filetype("./setup/$__f") === 'file' && preg_match('/\.setup\.php$/', $__f)) {
            array_push($__y, $__f);
        }
    }
    closedir($__x);
    sort($__y);
    foreach ($__y as $__f) {
        require_once ("./setup/$__f");
    }
    unset($__y);
    unset($__f);
    unset($__x);
}

?>
