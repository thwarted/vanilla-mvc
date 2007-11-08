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

