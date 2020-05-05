<?php
	$tmtest_initial_table_creation_queries = [
		'create table favourites (' .
			'favouriteid int unsigned not null primary key auto_increment,' .
			'userid int unsigned not null,' .
			'thing_id int unsigned not null' .
		') CHARSET=utf8 COLLATE utf8_unicode_ci',

		'create table things (' .
			'thingid int unsigned not null primary key auto_increment,' .
			'thingname varchar(20) not null' .
		') CHARSET=utf8 COLLATE utf8_unicode_ci',

		'create table users (' .
			'userid int unsigned not null primary key auto_increment,' .
			'username varchar(20) not null,' .
			'email varchar(150) not null,' .
			'password char(32) not null,' .
			'date timestamp,' .
			'favourite_int int not null,' .
			'favourite_float float not null,' .
			'biography text not null,' .
			'homepage varchar(100)' .
		') CHARSET=utf8 COLLATE utf8_unicode_ci',
	];

	$tmtest_initial_table_data = [
		'favourites' => [
			[
				'favouriteid' => 1,
				'userid'      => 1,
				'thing_id'    => 1,
			],
			[
				'favouriteid' => 2,
				'userid'      => 1,
				'thing_id'    => 4,
			],
			[
				'favouriteid' => 3,
				'userid'      => 2,
				'thing_id'    => 3,
			],
		],

		'things' => [
			[
				'thingid'   => 1,
				'thingname' => 'Elephant',
			],
			[
				'thingid'   => 2,
				'thingname' => 'Monkey',
			],
			[
				'thingid'   => 3,
				'thingname' => 'Octopus',
			],
			[
				'thingid'   => 4,
				'thingname' => 'Petrel',
			],
		],

		'users' => [
			[
				'userid'          => '2',
				'username'        => 'ben',
				'email'           => 'ben@ben.am',
				'password'        => '60b725f10c9c85c70d97880dfe8191b3',
				'date'            => '2014-11-06 18:15:17',
				'favourite_int'   => '7',
				'favourite_float' => '0.1',
				'biography'       => 'I would make an absolutely terrible giraffe.',
				'homepage'        => 'http://ben.am',
			],
			[
				'userid'          => '3',
				'username'        => 'mrperson',
				'email'           => 'person@somewhere.com',
				'password'        => '2cd6ee2c70b0bde53fbe6cac3c8b8bb1',
				'date'            => '2014-11-06 18:15:17',
				'favourite_int'   => '9',
				'favourite_float' => '0.3',
				'biography'       => 'This string contains a sentence, the contents of which has been intentionally designed in order that the total length of the string, including a final punctuation mark in the form of a period, is no more or less than two hundred and fifty five characters.',
				'homepage'        => 'http://www.mrperson-12345.com',
			],
			[
				'userid'          => '4',
				'username'        => 'emilia',
				'email'           => 'emilia@somewhere.com',
				'password'        => '2cd6ee2c70b0bde53fbe6cac3c8b8bb1',
				'date'            => '2014-11-06 18:15:17',
				'favourite_int'   => '9',
				'favourite_float' => '0.3',
				'biography'       => 'This string contains a sentence, the contents of which has been intentionally designed in order that the total length of the string, including a final punctuation mark in the form of a period, is no more or less than two hundred and fifty five characters.',
				'homepage'        => 'http://www.emilia-jet-propulsion-ltd.co.uk',
			],
		],
	];
