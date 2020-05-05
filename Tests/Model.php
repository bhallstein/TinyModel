<?
	/*
	 * Model.php
	 *
	 * Define a model for our test setup
	 * Added 6/11/2014 by Ben Hallstein
	 *
	 */


	require('../TinyModel.php');
	
	
	// Define tables for testing
	
	class User extends TinyModel {
		const userid   = 'int';
		const username = 'varchar alphanumeric maxlength=20 notnull';
		const email    = 'varchar email maxlength=150 notnull';
		const password = 'varchar maxlength=32 notnull';
		const date     = 'timestamp';
		const favourite_int   = 'int positive notnull';
		const favourite_float = 'float positive notnull';
		const biography = 'text maxlength=255 notnull';
		const homepage  = 'varchar url maxlength=100';
	}
	
	class Thing extends TinyModel {
		const thingid   = 'int';
		const thingname = 'varchar alphabetical maxlength=24 notnull';
	}
	
	class Favourite extends TinyModel {
		const favouriteid = 'int';
		const userid      = 'int notnull';
		const thing_id     = 'int notnull';
	}
	
	
?>
