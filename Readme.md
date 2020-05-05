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

*Return value:* the number of affected rows, or `false` if the query failed, or an array of errors if the inserted values did not match the field types specified as the constants in the subclass definition.


## insert

`insert()`

Insert an object into its corresponding table.

e.g. Insert a favourite for Jimmy into the `favourites` table.

    $f = new Favourite;
    $f->userid = 12;
    $f->thingid = $thingid;
    $f->insert();

*Return value:* the id of the inserted row, or `false`if the query failed, or an array of errors if the inserted values did not match the field types specified as the constants in the subclass definition.


## Usage in a web applcation

Typically, then, you define your TinyModel in a single file, like so:

    require_once($pathToRoot . 'helperFunctions.php');
    require_once('TinyModel.php');
    DB_connect();
    
    /*
     * Model.php - MyWebApp's model definitions
     * 
     */
    
    class User extends Model {
    	const userid   = 'int';
    	const username = 'alphanumeric';
    	const email    = 'email';
    	const password = 'text';
    	const salt     = 'alphanumeric';
    }
    
    class Session extends Model {
    	const userid       = 'int';
    	const sessiontoken = 'alphanumeric';
    	const date         = 'timestamp';
    }
    
    class Item extends Model {
    	const itemid      = 'int';
    	const name        = 'text';
    	const description = 'text'; 
    	const userid      = 'int';
    	const categoryid  = 'int';
    	const date        = 'int';
    }
    
    class Category extends Model {
    	const categoryid = 'int';
    	const name       = 'text';
    }

The controller layer then generally consists of calling the `fetch`, `update` and `insert` methods of your subclasses:

    /*
     * action_EditItem.php - edit an item
     *
     */
    
    require_once($pathToRoot . 'M/TinyModel.php');
    
    // Authenticate
    include('i_authenticate.php');
    if ($auth_error) exit('noauth');
    	
    $itemid = (int) $_GET['i'];
    
    $r = Item::update(
    	array(
    		'name' => urldecode($_GET['name']),
    		'description' => urldecode($_GET['desc'])
    	),
    	array('itemid' => $itemid, 'userid' => $auth_userid)
    );
    if ($r === false || is_array($r)) {
    	// oh dear
    }
	// success


## License

TinyModel is published under the MIT license.

Ben Hallstein
