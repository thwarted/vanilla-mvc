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

mkdir setup
mkdir models
mkdir views
mkdir controllers

mkdir media

echo 'This site is not configured yet.' > index.html

echo 'Options -Indexes' > media/.htaccess
echo 'RewriteEngine off' >> media/.htaccess


echo 'RewriteEngine On' > .htaccess
echo "RewriteBase $DOCROOT" >> .htaccess
echo 'RewriteRule ^.*$ vanilla/dispatch.php' >> .htaccess
echo >> .htaccess
echo '# the media/ directory needs an .htaccess that does RewriteEngine Off' >> .htaccess

