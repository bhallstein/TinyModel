# TinyModel

TinyModel is a PHP superclass that lets you very easily define the Model layer of your web application, and handles the communication between application code and database, returning user-friendly nested objects.

## Defining tables

To configure TinyModel, you create a set of subclasses, each representing a table in your database schema. The columns of the table are defined using class constants, specifying the name and type of the column, and optional restrictions on the values that may be inserted into it.

### Class & table names

With TinyModel, the name of the table is the (lowercase) plural of the name of the class. To define a table `users`, you create a class called `User`.

### Column specification

To define a column, you create a class constant with the same name as the column, and initialize it with a *column specification string*, with the format `"type [restrictions]"`:

- type: one of the following: `int`, `float`, `varchar`, `text`, `timestamp`
- restrictions: one or more of: `alphabetical`, `alphanumeric`, `email`, `url`, `positive`, `notnull`, `maxlength=N`

### Example of a class

If you have tables `users` and `items`, you might create two classes as follows:

    class User extends TinyModel {
    	const userid   = 'int notnull';
    	const username = 'varchar alphanumeric maxlength=20';
    	const email    = 'varchar email';
    }
    
    class Items extends TinyModel {
    	const itemid   = 'int notnull';
    	const userid   = 'int notnull';
    	const itemname = 'varchar maxlength=20';
    }

Interacting with these tables is then very straightforward, via the three methods detailed below.


## Methods

### fetch

`fetch($conditions, $joins)`

Static method. Attempt to fetch items from the database. Return values:

- an array representing the returned object(s) on success
- false on database error

Parameters:

- *conditions*

A Condition object, or an array of Condition objects, specifying what should be returned by the query. For instance, to only return rows where the userid is 76:

    new Condition('userid', 76)

You can specify multiple nested conditions, and the relationships between them (`and` or `or`), by passing a nested array. For details, see below under Conditions. At least one valid Condition object is required.

Static method. Returns one ore more objects matching the given condition(s). Any successful joins will be represented as sub-objects of their parents.

- *joins*

An optional Join object, or an array of Join objects, specifying joins to perform from results in this table to further tables.

*e.g.* Fetch user(s) with id 76, joined to any favourites, joined to those favourites themselves:

    User::fetch(
        new Condition('userid', 76),
        new Join('Favourite', 'userid', new Join('Thing', 'thingid')))
    );


### update

`update($updates, $conditions)`

Static method. Updates objects matching the given condition(s). Return values:

- the number of affected rows on success
- an array of errors if the inserted values did not match the field types specified as the constants in the subclass definition
- false on database error

Parameters:

- *updates*

An associative array of updates to perform, where the key specifies the column, and the value the new entry for that column.

- *conditions*

A Condition object, or an array of Condition objects governing which rows in the table will be updated with new value(s).

*e.g.* Update username of user with userid 12 to ‘jimmy’:

    User::update(
        array('username' => 'jimmy'),
        new Condition('userid', 12)
    );


### insert

`insert()`

Instance method. Insert an object into its corresponding table. You first create an instance, filling out its properties, then simply call `insert()` on it. Return values:

- the id of the inserted row on success
- an array of errors if insert values did not match the field types or restrictions of the table columns
- false on database error

*e.g.* Insert a favourite for Jimmy into the `favourites` table.

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
- *columns:* The name of the column(s) on which to join, as a string. The columns must have the same name in both tables. If an array of strings is passed, each specified column must contain the same datum in both tables for the rows to be joined.
- *joins:* Optionally, a further Join or array of Joins to perform off the joined table.


### Password columns

TinyModel handles columns named `password` automatically. It assumes the table has another column named `salt`. When a Condition is encountered with the column name `password`, TinyModel tests the supplied value against `md5(sha(concat(salt, $value)))`.

At present, this is the only way passwords are handled in TinyModel. It has the benefit of preventing a web app from storing passwords in retrievable form. A system to allow a dev-customisable MySQL function for handling passwords is a possibility for the future.


## Usage in a web application

For a simple web app, you might typically define your TinyModel subclasses in a single file, thereby specifying the tables you wish to communicate with.

The controller layer includes this file, and can then interact with the subclasses, making calls to TinyModel’s `fetch`, `update` and `insert` methods.

For a functioning example, see the file `demo.php`. (You will need to need to create the relevant database and tables.)

## License

TinyModel is published under the open source MIT license, and comes with no waranty whatsoever.

© Ben Hallstein
