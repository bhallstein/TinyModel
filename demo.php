<?
	require_once('TinyModel.php');
	mysql_connect('127.0.0.1', 'testuser', 'testpass');
	mysql_select_db('testdb');
	mysql_query('set names "utf8"');
	
	// Define our tables
	
	class User extends TinyModel {
		const userid   = 'int notnull';
		const username = 'varchar alphanumeric maxlength=20';
		const email    = 'varchar email';
		const password = 'varchar text';
		const salt     = 'varchar alphanumeric';
		const date     = 'timestamp';
	}
	
	class Thing extends TinyModel {
		const thingid   = 'int notnull';
		const thingname = 'varchar alphanumeric';
	}
	
	class Favourite extends TinyModel {
		const favouriteid = 'int notnull';
		const userid      = 'int notnull';
		const thingid     = 'int notnull';
	}
	

	echo '<pre>';
	
	
	// Insert a user
	
	$u = new User;
	$u->username = 'geoff';
	$u->email = 'something@somewhere.com';
	$u->password = 'blah';
	$u->salt = 'alghlks';
	$r = $u->insert();
	if ($r === false)
		echo "coudn't add geoff!";
	else if (is_array($r)) {
		echo "invalid input adding user: ";
		print_r($r);
	}
	else {
		$geoff_id = $r;
		echo "geoff was inserted with id ", $r;
	}
	echo "\n";
	
	
	// Insert a thing for Geoff to enjoy
	
	$t = new Thing;
	$t->thingname = "squishy";
	$r = $t->insert();
	if ($r === false)
		echo "couldn't add a squishy";
	else if (is_array($r)) {
		echo "invalid input adding thing:\n";
		print_r($r);
	}
	else {
		$thing_id = $r;
		echo "added thing with id ", $thing_id;
	}
	echo "\n";
	
	
	// Update the object's name
	$r = Thing::update(
		array('thingname' => "tshirt"),
		new Condition('thingid', $thing_id)
	);
	if ($r === false)
		echo "couldn't update the squishy";
	else if (is_array($r)) {
		echo "invalid input updating the squishy:\n";
		print_r($r);
	}
	else {
		echo "updated ", $r, ' item' . ($r == 1 ? '' : 's') . " in 'things' table to have name 't-shirt'";
	}
	echo "\n";
	
	
	// Geoff favourites the object
	
	$f = new Favourite;
	$f->userid = $geoff_id;
	$f->thingid = $thing_id;
	$r = $f->insert();
 	if ($r === false)
		echo "couldn't add favourite";
	else if (is_array($r)) {
		echo "invalid input adding favourite:\n";
		print_r($r);
	}
	else {
		$fav_id = $r;
		echo "added favourite with id $fav_id";
	}
	echo "\n";
	
	
	// Fetch the user
	
	$r = User::fetch(
		new Condition('userid', $geoff_id),
		array(new Join('Favourite', 'userid', new Join('Thing', 'thingid'))),
		true
	);
	if (!is_array($r))
		echo "TinyModel::fetch() returned false\n";
	else {
		echo "got Geoff, and geoff's favourites table, and the things themselves:\n";
		print_r($r);
		$geoff_faves = $r[0]->favourites;
		$things = $geoff_faves[0]->things;
		echo "Geoff's first favourited object name: ", $things[0]->thingname;
	}
	
	echo '</pre>';
?>
