#!/bin/sh

if [ -e ./configure.sh ]; then
    echo "$0: please run this script one level up from the vanilla directory" 1>&2
    exit 1
fi

if [ ! -d ./vanilla -o ! -f ./vanilla/dispatch.php ]; then
    echo "$0: the directory you run this script from must contain the vanilla directory" 1>&2
    exit 1
fi

mkdir -p {media,views}/example

if [ ! -e media/example/show.js ]; then
cat > media/example/show.js <<'EOF'
/*  this is the example
 *  javascript file
 *  it does nothing
 */
EOF
fi

if [ ! -e views/example/show.tpl ]; then
cat > views/example/show.tpl <<'EOF'
<head>
<title>Example Vanilla App</title>
{foreach from=$stylesheets item=css}
    <link rel="{$css.relation}" type="text/css" {if $css.media}media="{$css.media}"{/if} {if $css.title}title="{$css.title}"{/if} href="{$css.url}"></script>
{/foreach}
{foreach from=$javascripts item=js}
    <script type="text/javascript" src="{$js.url}"></script>
{/foreach}
</head>
<body>
{$fm->start()}
<dl>
{foreach from=$fm item=i}
    <dt>{$i->label_html()}{if $i->message()}<span style="font-size: 80%; color: red; padding-left: 4em;">{$i->message()}</span>{/if}</dt>
    <dd>{$i->html()}</dd>
{/foreach}
</dl>
{$fm->end()}

<table border="1">
<thead>
    <tr>
        <th colspan="2">Name</th>
        <th>Phone</th>
    </tr>
</thead>
<tbody>
    {foreach from=$customers item=c}
    <tr>
        <td><a href="{$c->deletelink}">delete</a></td>
        <td>{$c->name}</td>
        <td>{$c->phonenumber}</td>
    </tr>
    {/foreach}
</tbody>
</table>

{debugbox}
</body>
EOF
fi

if [ ! -e media/example/show-styles.css ]; then
cat > media/example/show-styles.css <<'EOF'
/* relation: stylesheet
 * media: screen
 * title: example
 */

form#names input {
    border: 1px solid #ccc;
}

form#names input:focus {
    border: 1px solid black;
}

table {
    border: 1px solid black;
}

table th {
    border: 0px;
    border-bottom: 2px solid black;
}

table td {
    border: 0px;
    padding: 5px;
}
EOF
fi

if [ ! -e models/examplecustomer.php ]; then
cat > models/examplecustomer.php <<"EOF"
<?php

class examplecustomer {
    
    public static function find($id=NULL) {
        global $exampledbh;

        if (isset($id)) {
            $sth = $exampledbh->prepare("select * from customers where id = ? limit 1");
            $sth->execute($id);
            $class = get_class();
            return $sth->fetchrow_object($class);
        } else {
            $sth = $exampledbh->prepare("select * from customers");
            $sth->execute();
            $class = get_class();
            $r = array();
            while($x = $sth->fetchrow_object($class)) {
                $r[] = $x;
            }
            return $r;
        }
    }

    public function commit() {
        global $exampledbh;

        if ($this->name && $this->phonenumber) {
            $this->name = trim($this->name);
            $this->phonenumber = preg_replace('/[^0-9-]/', '', $this->phonenumber);
            if (!isset($this->id) || !$this->id) {
                $this->id = NULL;
                $sth = $exampledbh->prepare("insert into customers (name, phonenumber) values (?, ?)");
                $x = $sth->execute($this->name, $this->phonenumber);
            } else {
                $sth = $exampledbh->prepare("update customers set name = ?, phonenumber = ? where id = ?");
                $x = $sth->execute($this->name, $this->phonenumber, $this->id);
            }
            d($x);
            if (!$this->id) {
                $this->id = $sth->insert_id();
            }
        } else {
            d("name and phone not set");
        }
    }

    public function delete() {
        global $exampledbh;

        if ($this->id) {
            $exampledbh->do_("delete from customers where id = ?", NULL, $this->id);
        }
        unset($this->id);
        unset($this->name);
        unset($this->phonenumber);
    }

}

EOF
fi

if [ ! -e setup/example-database.setup.php ]; then
cat > setup/example-database.setup.php <<'EOF'
<?php

$_dbname = 'vanilla/docs/samples/exampledb.sqlite3';
if (!is_writable($_dbname) || !is_writable(dirname($_dbname))) {
    header("Content-type: text/html");
    print "The example code requires that<ul><li>$_dbname</li><li>".dirname($_dbname)."</li></ul> be writable.";
    exit;
}

$exampledbh = DBI::connect("dbi:sqlite3:dbname=$_dbname");

if (!$exampledbh) {
    lib::internal_error("example database connection error");
}

EOF
fi

if [ ! -e controllers/example.php ]; then
cat > controllers/example.php <<'EOF'
<?php

require_once "models/examplecustomer.php";

class controller_example extends base_controller {
    private $cform;

    public function __construct() {
        parent::__construct();

        # put additional constructor stuff here
        # this is a good place to create forms that are used by all methods
        # in this controller
    }

    protected function in_valid_context($method=NULL) {
        # you'll want to raise ContextException here
        # if, for example, this controller requires
        # a session to be logged in, and it's not
        return true;
    }

    public function verify_name($value, $fieldname, $originform) {
        if (preg_match('/\d/', $value)) {
            return array(false, $value, 'names can not contain numbers');
        }
        return array(true, $value, NULL);
    }

    function verify_US_phonenumber($value, $fieldname, $originform) {
        if (preg_match('/[a-z]/i', $value)) {
            return array(false, $value, "US phone numbers can not contain letters");
        }
        $v = preg_replace('/[^0-9]/', '', $value);
        if (preg_match('/^1/', $v)) {
            return array(false, $value, "US phone numbers can not start with 1");
        }
        if (strlen($v) == 10 && preg_match('/^(\d{3})(\d{3})(\d{4})$/', $v, $m)) {
            list($all, $areacode, $exchange, $num) = $m;
            # normalize the value
            return array(true, "$areacode-$exchange-$num", NULL);
        }
        d(($m));
        return array(false, $value, "US phone numbers are 10 digits long");
    }

    protected function create_form() {
        $this->cform = new form('names');
        $this->cform[] = new form_input_text('name');
        $this->cform['name']->label('Name')
                                 ->required()
                                 ->verify_using(array($this, 'verify_name'));;

        $this->cform[] = new form_input_text('phonenumber');
        $this->cform['phonenumber']->label('Phone')
                                   ->required()
                                   ->verify_using(array($this, 'verify_US_phonenumber'));

        $this->cform[] = new form_input_submit('create', 'create');

        $this->cform->method('post');
        $this->cform->action(url(array($this, 'process')));
    }

    public function show() {
        $this->create_form();

        $this->viewname = "example/show.tpl";

        $this->view->assign('vars', $_SERVER);
        $this->view->assign('fm', $this->cform);

        $c = examplecustomer::find();
        $c = array_map(array($this, 'add_delete_links'), $c);
        $this->view->assign('customers', $c);
    }

    public function process() {
        $this->create_form();

        if ($this->cform->submitted()) {
            if ($this->cform->verify()) {
                #d($this->cform->dump());
                $newcust = new examplecustomer();
                $newcust->name = $this->cform['name']->value();
                $newcust->phonenumber = $this->cform['phonenumber']->value();
                $newcust->commit();
                throw new HTTPException("moved", 302, url(array($this, 'show')));
            }
        }

        $this->viewname = "example/show.tpl";
        $this->view->assign('fm', $this->cform);
        $c = examplecustomer::find();
        $c = array_map(array($this, 'add_delete_links'), $c);
        $this->view->assign('customers', $c);

    }

    public function add_delete_links($c) {
        $c->deletelink = url(array($this, 'deletecust'), $c->id);
        return $c;
    }

    public function deletecust($id) {
        $c = examplecustomer::find($id);
        $c->delete();
        throw new HTTPException("moved", 302, url(array($this, 'show')));
    }

}

EOF
fi

