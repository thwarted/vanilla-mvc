    # this file is included in all automatically generated Models at run-time

    # defer these static functions to SchemaTable's definition of them
    # to avoid parsing the source to figure this out in the definitions
    # on Model
    # see http://bugs.php.net/bug.php?id=12622
    #     http://bugs.php.net/bug.php?id=30934
    #     http://bugs.php.net/bug.php?id=30235
    static public function find() {
        $a = func_get_args();
        return self::_find(__CLASS__, $a);
    }

    static public function find_first() {
        $a = func_get_args();
        return self::_find_first(__CLASS__, $a);
    }
