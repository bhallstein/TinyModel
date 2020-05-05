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
		public static function describe() {
			return [
				'userid'          => 'id',
				'username'        => 'varchar alphanumeric maxlength=20 notnull',
				'email'           => 'varchar email maxlength=150 notnull',
				'password'        => 'varchar maxlength=32 notnull',
				'date'            => 'timestamp',
				'favourite_int'   => 'int positive notnull',
				'favourite_float' => 'float positive notnull',
				'biography'       => 'text maxlength=255 notnull',
				'homepage'        => 'varchar url maxlength=100',
			];
		}
	}
	
	class Thing extends TinyModel {
		public static function describe() {
			return [
				'thingid'   => 'id',
				'thingname' => 'varchar alphabetical maxlength=24 notnull',
			];
		}

	}
	
	class Favourite extends TinyModel {
		public static function describe() {
			return [
				'favouriteid' => 'id',
				'userid'      => 'int notnull',
				'thing_id'    => 'int notnull',
			];
		}
	}
	
	
?>
