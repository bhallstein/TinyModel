# TinyModel

TinyModel is a PHP superclass for defining the Model layer of your web application. It handles the communication between application and database, and returns friendly nested objects, even for arbitrary joins.

## Examples

### Example 1: Authenticating a user

    $conn = new PDO('mysql:host=127.0.0.1;charset=utf8;...');
    TinyModel::setConnection($conn);
    
    $p_username = $_POST['username'];
    $p_password = $_POST['password'];
    
    class User extends TinyModel {
        const userid = 'int';
        const username = 'varchar alphanumeric maxlength=30 notnull';
        const password = 'char maxlength=60 notnull';
    }
    
    $res = User::fetch(
        new Condition('username', $p_username)
    );
    if ($res->status !== TMResult::Success || count($res->result) != 1) {
        invokeView('loginError', $res);
    }
    else {
        $db_pwd = $res->result[0]->password;
        $authenticated = password_verify($p_password, $db_pwd);
        if ($authenticated)
            invokeView('loginSucceeded', $res);
        else
            invokeView('loginError', $res);
    }

### Example 2: Fetching with joins

TinyModel extracts all results into objects of the classes you create. If there are joins, it extracts joined results into sub-objects, attaching an array of them to the parent object. This can be done recursively:

    $conn = new PDO('mysql:host=127.0.0.1;charset=utf8;...');
    TinyModel::setConnection($conn);
    
    class User extends TinyModel {
        const userid = 'int';
        const username = 'varchar alphanumeric maxlength=30 notnull';
        const password = 'char maxlength=60 notnull';
        const email = 'varchar email maxlength=100';
    }
    
    class Favourite extends TinyModel {
        const faveid = 'int';
        const userid = 'int not null';
        const itemid = 'int not null';
    }
    
    class Item extends TinyModel {
        const itemid = 'int';
        const name = 'varchar maxlength=40 notnull';
    }
    
    $res = User::fetch(
        new Condition('userid', $p_userid),
        new Join('Favourite', 'userid', new Join('Item', 'itemid))
    );
    if ($res->status != TMResult::Success) { ... }
    else {
        echo json_encode($res->result, JSON_PRETTY_PRINT);
    }

This outputs:

    [
        {
            "userid": 269,
            "username": "someone",
            "email": "someone@somewhere.com",
            "password": "~",
            "favourites": [
                {
                    "favouriteid": 220,
                    "userid": 269,
                    "item": 241,
                    "items": [
                        {
                            "itemid": 241,
                            "name": "tshirt"
                        }
                    ]
                }
            ]
        }
    ]


## Defining tables

To configure TinyModel, you define a set of subclasses, each of which represents a table in your database. Class constants specify the name and type of each column, and optional restrictions on its values, which TinyModel validates when inserting/update values, and 

### Class & table names

The name of the table must be the (lowercase) plural of the name of the class. e.g. For a table `users`, create a class called `User`. TinyModel pluralises the class name to find the table name (but bear in mind it doesn’t know about invariant words such as 'Sheep').

### Column specification

To define a column, you create a class constant with the same name as the column, and initialize it with a *column specification string*, with the format `"type [restrictions]"`:

- type: one of the following: `int`, `float`, `char/varchar` (these are equivalent), `text`, `timestamp`
- restrictions: one or more of: `alphabetical`, `alphanumeric`, `email`, `url`, `positive`, `notnull`, `maxlength=N`

**Note on notnull:** ID columns are generally "not null" in the database, but should *not* be specified as such in your TinyModel column specification. This is because TinyModel needs to accept null values for ID columns at insert and update.

### Example of a class

If you have tables `users` and `items`, you might create two classes as follows:

    class User extends TinyModel {
        const userid   = 'int';
        const username = 'varchar alphanumeric maxlength=20 notnull';
        const email    = 'varchar email notnull';
    }
    
    class Items extends TinyModel {
        const itemid   = 'int';
        const userid   = 'int notnull';
        const itemname = 'varchar maxlength=20 notnull';
    }

You can then interacting with your tables via the three methods detailed below.


## TMResult

All methods return a TMResult object, returning the status of the query and any returned data.

Fields:

- **status:** one of the following statuses:
	- *TMResult::Success*
	- *InvalidData* – update or insert data failed validation
	- *InvalidConditions* – condition(s) failed validation
	- *InternalError* – TinyModel encountered an error executing the query
- **result:** returned data appropriate to the query:
	- *fetch:* an array of fetched objects
	- *update:* the number of rows updated
	- *insert:* the insert ID
- **errors:** error data appropriate to the query and type of failure:
    - *InvalidConditions:*
		- if there were *no* conditions, `errors` will be null
		- if some conditions did not pass validation, an array of errors in the form `column_name => validation_error` (see below)
	- *InvalidData:*
		- if some updates/inserts did not pass validation_error, an array of errors in the form `column_name => validation_error`
	- *InternalError:*
		- the result of calling errorInfo() on the PDO statement that failed

**Validation Errors:** these specify the type of error that was encountered when validating insert/update data or a condition:

- *NonexistentColumn:* the column does not exist in the table specification
- *InvalidValue:* the value failed column restrictions
- *UnknownObject:* a non-Condition object was passed where a Condition object was expected



## Methods

All three methods return a TMResult object encapsulating success/failure, and returned data or errors.

### fetch

`fetch($conditions, $joins, $debug = false)`

Static method. Attempt to fetch items from the database.

Parameters:

- *conditions*

A Condition object, or an array of Condition objects, specifying what should be returned by the query. For instance, to only return rows where the userid is 76:

    new Condition('userid', 76)

You can specify multiple nested conditions, and the relationships between them (`and` or `or`), by passing a nested array. For details, see Conditions. At least one valid Condition object is required for all queries.

- *joins*

An optional Join object, or an array of Join objects.

- *debug*

If true, prints out the generated `select` query before executing it.

*Example:* Fetch user(s) with id 76, joined to any favourites, joined to the favourited items:

    User::fetch(
        new Condition('userid', 76),
        new Join('Favourite', 'userid', new Join('Thing', 'thingid')))
    );


### update

`update($updates, $conditions, $debug = false)`

Static method. Updates objects matching the given condition(s).

Parameters:

- *updates*

An associative array of updates to perform, with the key specifying the column, and the value the new entry for that column.

- *conditions*

A Condition object or an array of Condition objects, governing which rows in the table will be updated with new value(s).

- *debug*

If true, prints out the query before executing it.

*Example:* Update username of the user with id 12 to ‘jimmy’:

    User::update(
        array('username' => 'jimmy'),
        new Condition('userid', 12)
    );


### insert

`insert()`

Instance method. Insert an object into its corresponding table. You first create an instance, filling out its properties, then simply call `insert()` on it.

*Example:* Insert a favourite for Jimmy into the `favourites` table.

    $f = new Favourite;
    $f->userid = 12;
    $f->thingid = $thingid;
    $f->insert();


### Condition objects

Condition objects are used to control which rows should be fetched or updated. The constructor takes the following values:

- *column:* The name of the column the condition applies to
- *value:* The value to test against
- *test:* One of the following constants. The default is Equals.
    - `Condition::Equals`
    - `NotEquals`
    - `LessThan`
    - `LessThanOrEquals`
    - `GreaterThan`
    - `GreaterThanOrEquals`
    - `Recent`
        - This is used to secify a timestamp column with a time value up to a certain amount of time in the past. When the `Recent` condition is used, the value field of the Condition specifies the number of seconds before the current timestamp that should be considered a match.
- *conjunction:* You can pass a nested array of Conditions to the `fetch` or `update` methods, allowing you to specify a set of conditions such as `where userid < 76 or (email = 'a@b.com' and username = 'geoff')`. The `conjunction` parameter specifies the conjunction with which the *following* condition or array of conditions relates to the current one: ‘or’ or ‘and’.

i.e. The aforementioned nested set of conditions would be represented as follows:

    User::fetch(
        array(
            new Condition('userid', 76, Condition::LessThan, Condition::_Or),
            array(
                new Condition('email', 'a@b.com'),
                new Condition('username', 'geoff')
            )
        )
    );

The default conjunction is `Condition::_And`.
    
    
### Join objects

You can pass a Join object or an array of Join objects to a call to `fetch`, to join the results returned from this table to other tables.

The constructor has the arguments:

- *class:* The name of the class (*not* the table name). For instance, `'User'`.
- *columns:* The name of the column(s) on which to join. You can pass:
    - a string – join tables on a single column 
    - an array of strings – join on several columns
    - an associative array – the key specifies the home column, and the value specified the away column
- *joins:* Optionally, a further Join (or array of Joins), from the away table to (an)other table(s).

When more than one column is provided for a join, *all* specified columns’ values must be the same in both tables for the row to be joined. i.e. the generated query is as follows:

    new Join(Thing, ['A', 'B'])
    –> ...left join things on users.A = things.A and users.B = things.B


### Password columns

In previous versions, applying a condition to a column named 'password' was handled separately - the supplied test value was concatenated with an assumed `salt` column, then SHA’d and then MD5’d, and the result tested against the stored value.

This has changed in TinyModel 0.91. Set password fields as ordinary `char` or `varchar` columns, and use PHP’s new `password_hash()` and `password_verify()` functions to generate values/test stored values against authentication attempts. (These functions use the bcrypt algorithm, and automatically incorporate a per-user salt.)


### Length restrictions & multibyte characters

It’s very good practice in the modern world to set the charset of tables to UTF8. Indeed, TinyModel considers this an assumption. But, note that:

- For char/varchar columns, as of MySQL 5.1, with a length of N, you can store N *characters* in the field.
- For text types, you have a byte limit, so the number of characters that can be stored is variable:
	- tinytext: 255 bytes
	- text: 65,535 bytes
	- mediumtext: 16,777,215 bytes
	- longtext: 4,294,967,295 bytes

i.e. the maxlength restriction for `char` or `varchar` columns will count characters, whereas for `text` columns it will count bytes.


## Usage in a web application

For a simple web app, you might typically define your TinyModel subclasses in a single file, thereby specifying the tables you wish to communicate with.

The controller layer includes this file, and can then interact with the subclasses, making calls to TinyModel’s `fetch`, `update` and `insert` methods.

For a functioning example, see the file `demo.php`. (You will need to need to create the relevant database and tables.)


## License

TinyModel is published under the MIT license, and comes with no warranty whatsoever.

© Ben Hallstein
