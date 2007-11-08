<?php

# This model class is a re-implementation of the example person model
# that pulls in rows from the database on-demand as it is iterated
# over

require_once "lib/form.php";

class person implements Iterator {
    private $_sth;
    private $_cur;
    private $_fetched;
    private $_exeargs;

    public static function find($name=NULL) {
        global $dbh;

        $c = get_class();
        $o = new $c();

        $q = "select * from person";

        $o->_exeargs = array();
        if (isset($name)) {
            $q .= " where name like ?";
            $o->_exeargs[] = "%".$name."%";
        }

        $o->_sth = $dbh->prepare($q);
        return $o;
    }

    # input and editing forms are often closely tied to the model
    # so here the model defines a ::form static function that builds
    # the base of the form
    public static function augment(form $f) {

        # add fields to the form
        # the form object looks like an array, here new form elements
        # are appended to the form using array pushing syntax
        $f[] = new form_input_text('person_name');
        $f[] = new form_input_text('person_phonenumber');

        # individual fields can be accessed using array notation
        # the keys used for array indexes are the name of the field
        $f['person_name']
                ->label('Name')
                ->required(true)
                ->verify_using(array('person', 'verify_name'));
        # notice how the calls can be chained together, some form field
        # methods allow that
        $f['person_phonenumber']
                ->label('Phone number')
                ->required(true)
                ->verify_using(array('person', 'verify_phonenumber'));

    }

    # when a new person model object is created, it can be populated 
    # with the contents of a submitted form, a form that was augmented
    # with the ::augment static method above
    public function populate_from(form $f) {
        if ($f->submitted() && $f->verify()) {
            $this->name = $f['person_name']->value();
            $this->phonenumber = $f['person_phonenumber']->value();
        }
    }

    public function commit() {
        global $dbh;

        $fields = array('name', 'phonenumber');
        $q = false;
        if (isset($this->id)) {
            $r = array();
            $v = array();
            foreach($fields as $f) {
                if (isset($this->$f)) {
                    $r[] = "$f = ?";
                    $v[] = $this->$f;
                }
            }
            if (count($r)) {
                $q = "update person set ".join(',', $r)." where id = ?";
                $v[] = $this->id;
            }
        } else {
            $r = array();
            $v = array();
            foreach($fields as $f) {
                if (isset($this->$f)) {
                    $r[] = $f;
                    $v[] = $this->$f;
                }
            }
            if (count($r)) {
                $r[] = 'createdate';
                $v[] = time();
                $q = "insert into person (".join(',', $r).") values (".join(',', array_fill(0, count($r), "?")).")";
            }
        }
        if ($q) {
            $sth = $dbh->prepare($q);
            $sth->execute_array($v);
            if (!isset($this->id)) {
                $this->id = $sth->insert_id();
            }
        }
    }

    public static function delete($id) {
        global $dbh;

        $sth = $dbh->prepare("delete from person where id = ?");
        $sth->execute($id);
        return $sth->affected_rows();
    }

    # the function used to verify the name field
    public function verify_name($value, $fieldname, $originform) {
        # the verification function returns three values:
        # - a boolean indicating that the field's value is valid
        # - a "cleaned up" value to replace the submitted value
        # - an error message
        if (preg_match('/\d/', $value)) {
            return array(false, trim($value), 'can not contain digits');
        }
        if (!preg_match('/^[\w ]+$/', $value)) {
            return array(false, trim($value), 'can contain only letters, numbers and spaces');
        }
        return array(true, trim($value), '');
    }

    # the function used to verify the phone field
    public function verify_phonenumber($value, $fieldname, $originform) {
        $value = trim($value);
        if (preg_match('/[a-wyz]/i', $value)) { # exclude x, for extensions
            return array(false, $value, 'phone number can not contain letters');
        }
        $t = preg_replace('/[^0-9x]/i', '', $value);
        if (!preg_match('/^(\d\d\d)(\d\d\d)(\d\d\d\d)((x\d+)?)$/i', $t, $m)) {
            return array(false, $value, "does not appear to be a US phone number");
        }
        list($all, $ac, $pr, $pn, $ext) = $m;

        # here, we send back a pretty-printed version
        return array(true, strtolower("($ac) $pr-$pn$ext"), '');
    }

    # Iterator interface
    public function current() {
        if (!$this->_fetched) {
            return $this->next();
        }
        return $this->_cur;
    }

    public function rewind() {
        $this->_sth->execute_array($this->_exeargs);
    }

    public function key() {
        return $this->_cur->id;
    }

    public function next() {
        $this->_fetched++;
        $this->_cur = $this->_sth->fetchrow_object(get_class());
        if ($this->_cur) {

            # pull in external references/foreign keys also
            # or the class could be written to load them
            # on demand

        }
        return $this->_cur;
    }

    public function valid() {
        if (!$this->_fetched) {
            $this->next();
        }
        return is_object($this->_cur);
    }

}

?>
