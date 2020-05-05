# TinyModel

TinyModel is a PHP superclass that handles the translation of database tables into nice usable objects and vice versa.

If you have a table `users` with columns `INT userid` and `VARCHAR(N) username` you would create a class `User` as follows:

    class User extends TinyModel {
    	const userid = 'int';
    	const username = 'alphanumeric';
    }
    
(TinyModel infers the table name from the class name, and assumes that the table is the lower case plural of the class. e.g. User => users; Thingy => thingies; etc.)

You can then interact with the database table via a User object, via the three following methods:

## fetch

`fetch($conditions, $joins, $use_or_conditions = false)`

Static method. Returns objects matching the given condition(s). Any successful joins will be represented as sub-objects of their parents.

e.g. Fetch users, joined to their favourites, joined to the specific things they have favourited:

    User::fetch(
        array('userid' => $geoff_id),
        array(new Join('Favourite', 'userid', new Join('Thing', 'thingid')))
    );

*Return value:* an array representing the fetched objects, or `false` if the query failed

## update

`update($updates, $conditions)`

Static method. Updates objects matching the given condition(s).

e.g. Update username of user with userid 12 to `jimmy`:

    User::update(
        array('username' => 'jimmy'),
        array('userid' => 12)
    );

*Return value:* the number of affected rows, or `false` if the query failed, or an array of errors if the inserted values were did not match the field types specified as constants in the subclass definition.

## insert

`insert()`

Insert an object into its corresponding table.

e.g. Insert a favourite for Jimmy into the `favourites` table.

$f = new Favourite;
$f->userid = 12;
$f->thingid = $thingid;
$f->insert();

*Return value:* the id of the inserted row, or `false`if the query failed, or an array of errors if the inserted values were did not match the field types specified as constants in the subclass definition.