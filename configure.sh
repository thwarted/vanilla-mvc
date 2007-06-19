#!/bin/sh

if [ -e ./configure.sh ]; then
    echo "$0: please run this script one level up from the vanilla directory" 1>&2
    exit 1
fi

if [ ! -d ./vanilla -o ! -f ./vanilla/dispatch.php ]; then
    echo "$0: the directory you run this script from must contain the vanilla directory" 1>&2
    exit 1
fi

DOCROOT=$1
shift

if [ "x$DOCROOT" = 'x' ]; then
    echo "Usage: $0 <document root>" 1>&2
    exit 1
fi

mkdir -p setup
mkdir -p models
mkdir -p views
mkdir -p controllers

mkdir -p media

if [ ! -e index.html ]; then
echo 'This site is not configured yet.' > index.html
fi

if [ ! -e media/.htaccess ]; then
cat > media/.htaccess <<"EOF"
Options -Indexes
RewriteEngine off
EOF
fi

if [ ! -e ./.htaccess ]; then
cat > .htaccess <<EOF
RewriteEngine On
RewriteBase $DOCROOT
RewriteRule ^.*$ vanilla/dispatch.php

# the media/ directory needs an .htaccess that does RewriteEngine Off
EOF
fi

if [ ! -e setup/README ]; then
cat > setup/README <<"EOF"
The files in this directory are executed at the following times:

global_conf.php (required)
    The first thing that is executed.  Should be used to setup
    the PHP environment, such as changing the error_reporting()
    level, if necessary.
    There are also some settings in here that should be modified 
    on a per-site basis.


*.setup.php (optional)
    Executed in sorted order.  Should be used to open database
    connections, start sessions, etc.


smarty_custom.php (required, but may be an empty class)
    Defines a single class whose methods will be used to extend
    smarty through additional modifiers, functions, etc.


template_conf.php (optional)
    If this file exists, it will be included at every point
    when a new smarty object is instanciated.  Any customizations
    to the smarty object should happen here.
EOF
fi

if [ ! -e setup/template_conf.php ]; then
cat > setup/template_conf.php <<"EOF"
<?php

# This file is included by the smarty factory everytime a
# smarty object is created

# the variable $smarty is the smarty instance that can be modified at this point

# $smarty->assign('example', 'examplevalue');

?>
EOF
fi

if [ ! -e setup/smarty_custom.php ]; then
cat > setup/smarty_custom.php <<"EOF"
<?php

# custom smarty extensions can be defined here
# this object, smarty_custom, will be searched for
# methods and add them to the smarty object

class smarty_custom {

    # methods named with a prefix of func_ define
    # a smarty function

    #public function func_a($params, &$smarty) {

    # methods named with a prefix of modifier_
    # define a smarty modifier

    #public function modifier_b($v) {

}

?>
EOF
fi

if [ ! -e setup/global_conf.php ]; then
cat > setup/global_conf.php <<"EOF"
<?php

error_reporting(E_ALL);

# set this to a pcre regular expression that matches client addresses
# that you want to show debug information for in the output
$_SERVER['internal_hosts_regexp'] = '';

# set this to the controller name and method that implements 
# the "default" page
$_SERVER['default_controller'] = array('example', 'list');

?>
EOF
fi


