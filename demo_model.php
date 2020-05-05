<?php

	require_once('TinyModel.php');

	// Create connection
	try {
		$conn = new PDO(
			'mysql:host=127.0.0.1;dbname=tinymodel_demo;charset=utf8',
			'testuser',
			'testpass'
		);
	}
	catch (PDOException $e) {
		echo 'Error connecting to the database';
		exit;
	}
	TinyModel::setConnection($conn);


	// Define our tables

	class User extends TinyModel {
		public static function describe() {
			return [
				'userid'   => 'int positive notnull',
				'muffins'  => 'int positive notnull',
				'username' => 'varchar alphanumeric maxlength=20',
				'email'    => 'varchar email maxlength=150',
				'password' => 'varchar maxlength=32',
				'salt'     => 'varchar alphanumeric',
				'date'     => 'timestamp notnull',
			];
		}
	}

	class Thing extends TinyModel {
		public static function describe() {
			return [
				'thingid'   => 'int positive notnull',
				'thingname' => 'varchar alphanumeric',
			];
		}
	}

	class Favourite extends TinyModel {
		public static function describe() {
			return [
				'favouriteid' => 'int positive notnull',
				'userid'      => 'int positive notnull',
				'thingid'     => 'int positive notnull',
			];
		}
	}

