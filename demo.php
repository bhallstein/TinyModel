<?php

	require_once(__DIR__ . '/demo_model.php');

	echo '<pre>';
	
	
	// Insert a user, Geoff
	
	$u = new User;
	$u->username = 'mrgeoff';
	$u->email = 'something@somewhere.com';
	$u->password = 'blah';
	$u->salt = 'alghlks';
	$r = $u->insert();
	if ($r->status != TMResult::Success) {
		echo "couldn't add '{$u->username}':\n";
		var_dump($r);
		exit;
	}
	$geoff_id = $r->result;
	echo "- '{$u->username}' was inserted with id $geoff_id\n";
	
	
	// Insert a thing for Geoff to enjoy
	
	$t = new Thing;
	$t->thingname = "something";
	$r = $t->insert();
	if ($r->status != TMResult::Success) {
		echo "couldn't add the '{$t->thingname}':\n";
		var_dump($r);
		exit;
	}
	$thing_id = $r->result;
	echo "- added thing '{$t->thingname}' with id $thing_id\n";
	
	
	// Update the object's name
	
	$new_name = 'tshirt';
	$r = Thing::update(
		array('thingname' => $new_name),
		new Condition('thingid', $thing_id)
	);
	if ($r->status != TMResult::Success) {
		echo "couldn't update the item:\n";
		var_dump($r);
		exit;
	}
	echo "- updated ", $r->result, ' item' . ($r->result == 1 ? '' : 's') . " in 'things' table to have name '$new_name'\n";
	
	
	// Geoff favourites the object
	
	$f = new Favourite;
	$f->userid = $geoff_id;
	$f->thingid = $thing_id;
	$r = $f->insert();
 	if ($r->status != TMResult::Success) {
		echo "couldn't add favourite:\n";
		var_dump($r);
	}
	$fav_id = $r->result;
	echo "- added favourite with id $fav_id\n";
	
	
	// Fetch the user
	
	$r = User::fetch(
		new Condition('userid', $geoff_id),
		new Join('Favourite', 'userid', new Join('Thing', 'thingid')),
		true
	);
	if ($r->status != TMResult::Success) {
		echo "couldn't fetch geoff & his favourites:\n";
		var_dump($r);
	}
	echo "- got geoff, and geoff's favourites table, and the things themselves:\n";
	print_r($r->result);
	$geoff_faves = $r->result[0]->favourites;
	$things = $geoff_faves[0]->things;
	echo "- Name of first favourited object: ", $things[0]->thingname;
	echo " :) \n";
	
	echo '</pre>';
?>
