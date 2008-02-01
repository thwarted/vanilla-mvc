# this file is included in all automatically generated Models at run-time

# all models need to at least have this defined
public static $__table__;

# defer these static functions to SchemaTable's definition of them
# avoid reading the source to figure this out in the definitions
# on Model
# see http://bugs.php.net/bug.php?id=30934
#     http://bugs.php.net/bug.php?id=30235
static public function find() {
    $a = func_get_args();
    return call_user_func_array(array(self::$__table__, 'find'), $a);
}

static public function find_first() {
    $a = func_get_args();
    return call_user_func_array(array(self::$__table__, 'find_first'), $a);
}

