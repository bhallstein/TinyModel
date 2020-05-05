<?php
	/*
	 * validation_test_data.php
	 *
	 * Provide data for testing TinyModel validation
	 *
	 */

	function getValidationTestData($type) {
		$resexp_noCol = function($colname) use ($type) {
			return new TMResult($type == 'condition' ? TMResult::InvalidConditions : TMResult::InvalidData,
			                    null,
			                    [ $colname => ValidationError::NonexistentColumn ]);
		};
		$resexp_invalidValue = function($fieldName) use ($type) {
			return new TMResult($type == 'condition' ? TMResult::InvalidConditions : TMResult::InvalidData,
			                    null,
			                    [ $fieldName => ValidationError::InvalidValue ]);
		};
		$resexp_success = function() use ($type) {
			return new TMResult(TMResult::Success,
			                    $type == 'condition' ? [ ] : 0);
		};

		// Data for column type restrictions
		$testData = [
			[
				'field' => 'monkeys',
				'value' => 7,
				'res_exp' => $resexp_noCol('monkeys', $type),
				'class' => User,
				'description' => 'col does not exist'
			],
			[
				'field' => 'userid',
				'value' => 'hi',
				'res_exp' => $resexp_invalidValue('userid'),
				'class' => User,
				'description' => 'id col, gets string'
			],
			[
				'field' => 'favourite_int',
				'value' => 'hi',
				'res_exp' => $resexp_invalidValue('favourite_int'),
				'class' => User,
				'description' => 'int col, gets string'
			],
			[
				'field' => 'username',
				'value' => 7,
				'res_exp' => $resexp_invalidValue('username'),
				'class' => User,
				'description' => 'var/char col, gets int'
			],
			[
				'field' => 'favourite_float',
				'value' => 'monkeys',
				'res_exp' => $resexp_invalidValue('favourite_float'),
				'class' => User,
				'description' => 'float col, gets string'
			],
			[
				'field' => 'biography',
				'value' => 7,
				'res_exp' => $resexp_invalidValue('biography'),
				'class' => User,
				'description' => 'text col, gets int'
			],


			// Test column value restrictions

			// Positive
			[
				'field' => 'userid',
				'value' => -12,
				'res_exp' => $resexp_invalidValue('userid'),
				'class' => User,
				'description' => 'col positive restriction (id)'
			],
			[
				'field' => 'favourite_int',
				'value' => -12,
				'res_exp' => $resexp_invalidValue('favourite_int'),
				'class' => User,
				'description' => 'col positive restriction (int)'
			],
			[
				'field' => 'favourite_float',
				'value' => -3.14159,
				'res_exp' => $resexp_invalidValue('favourite_float'),
				'class' => User,
				'description' => 'col positive restriction (float)'
			],

			// Notnull
			[
				'field' => 'favourite_int',
				'value' => null,
				'res_exp' => $resexp_invalidValue('favourite_int'),
				'class' => User,
				'description' => 'col notnull restriction'
			],

			// Maxlength
			[
				'field' => 'password',
				'value' => 'I am thirty two characters long.',
				'res_exp' => $resexp_success(),
				'class' => User,
				'description' => 'col maxlength restriction (char - characters success@32-ascii)'
			],
			[
				'field' => 'password',
				'value' => 'Î åm thîrty twø châráctërs løng.',
				'res_exp' => $resexp_success(),
				'class' => User,
				'description' => 'col maxlength restriction (char - characters success@32-utf)'
			],
			[
				'field' => 'password',
				'value' => 'I am longer than thirty two characters.',
				'res_exp' => $resexp_invalidValue('password'),
				'class' => User,
				'description' => 'col maxlength restriction (char - characters fail@>32-ascii)'
			],
			[
				'field' => 'biography',
				'value' => 'Thiz string contains a sentence, the contents of which has been intentionally designed in order that the total length of the string, including a final punctuation mark in the form of a period, is no more or less than two hundred and fifty five characters.',
				'res_exp' => $resexp_success(),
				'class' => User,
				'description' => 'col maxlength restriction (text - bytes success@255)'
			],
			[
				'field' => 'biography',
				'value' => 'Thîz s†rîñg contains a sentence, the contents of which has been intentionally designed in order that the total length of the string, including a final punctuation mark in the form of a period, is no more or less than two hundred and fifty five characters.',
				'res_exp' => $resexp_invalidValue('biography'),
				'class' => User,
				'description' => 'col maxlength restriction (text - bytes fail@255-utf)'
			],

			// Email & URLs
			[
				'field' => 'email',
				'value' => 'a@ben.am',
				'res_exp' => $resexp_success(),
				'class' => User,
				'description' => 'col email restriction (success w/ email)'
			],
			[
				'field' => 'email',
				'value' => 'ben at ben dot am',
				'res_exp' => $resexp_invalidValue('email'),
				'class' => User,
				'description' => 'col email restriction (fail w/ non-email)'
			],
			[
				'field' => 'homepage',
				'value' => 'http://ben.com',
				'res_exp' => $resexp_success(),
				'class' => User,
				'description' => 'col url restriction (success w/ url)'
			],
			[
				'field' => 'homepage',
				'value' => 'ben dot am',
				'res_exp' => $resexp_invalidValue('homepage'),
				'class' => User,
				'description' => 'col url restriction (fail w/ non-url)'
			],

			// Alphabetic/alphanumeric
			[
				'field' => 'username',
				'value' => 'ben9',
				'res_exp' => $resexp_success(),
				'class' => User,
				'description' => 'col alphanumeric restriction (success w/ alphanumeric)'
			],
			[
				'field' => 'username',
				'value' => 'mr_ben9',
				'res_exp' => $resexp_invalidValue('username'),
				'class' => User,
				'description' => 'col alphanumeric restriction (fail w/ non-alphanumeric)'
			],
			[
				'field' => 'thingname',
				'value' => 'thing',
				'res_exp' => $resexp_success(),
				'class' => Thing,
				'description' => 'col alphabetical restriction (success w/ A-Z)'
			],
			[
				'field' => 'thingname',
				'value' => 'thing2',
				'res_exp' => $resexp_invalidValue('thingname'),
				'class' => Thing,
				'description' => 'col alphabetical restriction (fail w/ non-A-Z)'
			],

			// Timestamps
			[
				'field' => 'date',
				'value' => '2014-11-06 01:01:01',
				'res_exp' => $resexp_success(),
				'class' => User,
				'description' => 'col timestamp (success w/ time-string)'
			],
			[
				'field' => 'date',
				'value' => '2014-11-06 oh hai there',
				'res_exp' => $resexp_invalidValue('date'),
				'class' => User,
				'description' => 'col timestamp (fail w/ non-time-string)'
			],
		];

		return $testData;
	}
