<?php
	/*
	 * TinyModelTest.php
	 *
	 * Unit testing for TinyModel
	 * Added 6/11/2014 by Ben Hallstein
	 *
	 */

	require_once(__DIR__ . '/Model.php');
	require_once(__DIR__ . '/TM_Generic_DB_TestCase.php');
	require_once(__DIR__ . '/testdata/validation_test_data.php');


	class TinyModelTest extends TM_Generic_DB_TestCase {

		protected function setUp() {
			TinyModel::setConnection(self::getPDO());
			parent::setUp();
		}

		protected function tearDown() {
			parent::tearDown();
		}

		// Test DB setup
		public function testDataBaseConnection() {
			global $tmtest_initial_table_data;

			$st = self::getPDO()->query('select * from users order by userid desc');
			$r = $st->execute();
			$row = $st->fetch(PDO::FETCH_ASSOC);
			$this->wrapAssert(assertEquals, [$row, $tmtest_initial_table_data['User'][2]], 'Database loaded');
		}

		public function test_Fetch() {
			// Fetch must have at least 1 condition
			$res = User::fetch([ ]);
			$res_exp = new TMResult(TMResult::InvalidConditions, null, null);
			$this->wrapAssert(assertEquals, [$res, $res_exp], 'Fetch: checks conditions - fails if none');


			// Fetch returns expected result with and-conditions
			$res = User::fetch([
				new Condition('favourite_int', 7),
				new Condition('userid', 1, Condition::GreaterThan)
			]);
			$this->wrapAssert(assertEquals, [$res->result[0]->userid, 2], 'Fetch: condition conjunctions: _And');


			// Or-conditions
			$res = User::fetch([
				new Condition('homepage', 'http://www.emilia-jet-propulsion-ltd.co.uk', Condition::Equals, Condition::_Or),
				new Condition('username', 'mrperson')
			]);
			$fetched_userids = [ ];
			foreach ($res->result as $res) $fetched_userids []= $res->userid;
			$this->wrapAssert(assertEquals, [$fetched_userids, [ 3, 4 ]], 'Fetch: condition conjunctions: _Or');


			// Nested conditions
			$res = User::fetch([
				new Condition('userid', 3, Condition::Equals, Condition::_Or),
				[
					new Condition('userid', 3, Condition::LessThan),
					new Condition('favourite_int', 7)
				]
			]);
			$fetched_userids = [ ];
			foreach ($res->result as $res) $fetched_userids []= $res->userid;
			$this->wrapAssert(assertEquals, [$fetched_userids, [ 2, 3 ]], 'Fetch: nested conditions');


			// Single join
			$res = User::fetch(
				new Condition('userid', 2),
				new Join(Favourite, 'userid')
			);
			$fetched_favids = [ ];
			foreach ($res->result[0]->favourites as $f) $fetched_favids []= $f->favouriteid;
			$this->wrapAssert(assertEquals, [$fetched_favids, [ 3 ]], 'Fetch: single join');


			// Nested join
			$res = User::fetch(
				new Condition('userid', 2),
				new Join(Favourite, 'userid', new Join(Thing, ['thing_id' => 'thingid']))
			);
			$thingname = $res->result[0]->favourites[0]->things[0]->thingname;
			$this->wrapAssert(assertEquals, [$thingname, 'Octopus'], 'Fetch: nested join');
		}

		public function test_Update() {
			// Update checks it has at least 1 condition
			$res = User::update(['username'=>'something'], [ ]);
			$res_exp = new TMResult(TMResult::InvalidConditions, null, null);
			$this->wrapAssert(assertEquals, [$res, $res_exp], 'Update: checks conditions - fails if none');

			// Updates that fail due to bad conditions do not alter DB state
			$res = User::update(['username' => 'benben'], new Condition('userid', 'muffins'));
			$this->wrapAssert(assertEquals, [$res->status, TMResult::InvalidConditions], 'Update: fails on bad conditions');
			$res = User::fetch(new Condition('userid', 0, Condition::GreaterThan));
			$this->wrapAssert(assertEquals, [$res->result, $this->expected_fetched_data['User']], 'Update: bad conditions do not alter DB');

			// Updates that fail due to bad values do not alter DB state
			$res = User::update(['username' => 'ben_nonalpanumeric'], new Condition('userid', 1));
			$this->wrapAssert(assertEquals, [$res->status, TMResult::InvalidData], 'Update: fails on bad values');
			$res = User::fetch(new Condition('userid', 0, Condition::GreaterThan));
			$this->wrapAssert(assertEquals, [$res->result, $this->expected_fetched_data['User']], 'Update: bad values do not alter DB');

			// Test updates that return success take effect in the DB
			$res = User::update(['username' => 'bhallstein'], new Condition('userid', 2));
			$this->wrapAssert(assertEquals, [$res->status, TMResult::Success], 'Update: succeeds when parameters OK');
			$res = User::fetch(new Condition('userid', 2));
			$this->wrapAssert(assertEquals, [$res->result[0]->username, 'bhallstein'], 'Update: successful updates applied as expected');
		}

		public function test_Insert() {
			// Inserts that fail due to bad values do not alter DB state
			$t = new Thing;
			$t->thingname = 'Honey Badger';
			$res = $t->insert();
			$this->wrapAssert(assertEquals, [$res->status, TMResult::InvalidData], 'Insert: fails on bad values');
			$res = Thing::fetch(new Condition('thingid', 0, Condition::GreaterThan));
			$this->wrapAssert(assertEquals, [$res->result, $this->expected_fetched_data['Thing']], 'Insert: bad values do not alter DB');

			// Test successful inserts work as expected
			$t = new Thing;
			$t->thingname = 'Stoat';
			$res = $t->insert();
			$this->wrapAssert(assertEquals, [$res->status, TMResult::Success], 'Insert: success on properly formed values');
			$res = Thing::fetch(new Condition('thingid', 0, Condition::GreaterThan));
			$exp = &$this->expected_fetched_data['Thing'];
			$exp_thing = new Thing;
			$exp_thing->thingid = 5;
			$exp_thing->thingname = $t->thingname;
			$exp []= $exp_thing;
			$this->wrapAssert(assertEquals, [$res->result, $exp], 'Insert: expected effect on table when successful');
		}

		public function test_ConditionValidation() {
			// Test condition field/value validation
			$conditionsTestData = getValidationTestData('condition');
			foreach ($conditionsTestData as $item) {
				$res = $item['class']::fetch(new Condition($item['field'], $item['value']));
				$this->wrapAssert(assertEquals,
				                  [ $res, $item['res_exp'] ],
				                  'Condition validation: ' . $item['description']);
			}
		}

		public function test_UpdateValidation() {
			$updatesTestData = getValidationTestData('update');
			$passingConditions = [
				'User' => new Condition('userid', 7),
				'Thing' => new Condition('thingid', 7),
			];
			foreach ($updatesTestData as $item) {
				$res = $item['class']::update([$item['field'] => $item['value']],
				                              $passingConditions[(string) $item['class']]);
				$this->wrapAssert(assertEquals,
				                  [ $res, $item['res_exp'] ],
				                  'Update validation: ' . $item['description']);
			}
		}

		public function testInsertValidation() {
			$insertsTestData = getValidationTestData('insert');

			// Inserts should fail when notnull columns have not been provided
			$f = new Favourite;
			$f->userid = 7;
			$res = $f->insert();
			$res_exp = new TMResult(TMResult::InvalidData, null, ['thing_id' => ValidationError::InvalidValue]);
			$this->wrapAssert(assertEquals, [$res, $res_exp], 'Insert validation: notnull column not provided');

			// Test value type checking
			$u = new User;
			$u->username        = 1;
			$u->email           = 2;
			$u->password        = 3;
			$u->date            = 5;
			$u->favourite_int   = 'eight';
			$u->favourite_float = 'thirteen';
			$u->biography       = 21.0;
			$u->homepage        = 34;

			$res = $u->insert();
			$res_exp = new TMResult(TMResult::InvalidData, null, [
				'username'        => ValidationError::InvalidValue,
				'email'           => ValidationError::InvalidValue,
				'password'        => ValidationError::InvalidValue,
				'date'            => ValidationError::InvalidValue,
				'favourite_int'   => ValidationError::InvalidValue,
				'favourite_float' => ValidationError::InvalidValue,
				'biography'       => ValidationError::InvalidValue,
				'homepage'        => ValidationError::InvalidValue,
			]);
			$this->wrapAssert(assertEquals, [$res, $res_exp], 'Insert validation: value type checking');

			// Test value restriction checking
			$u->username = 'ab_cd12';
			$u->email = 'ben at ben dot am';
			$u->password = 'I am more than thirty two characters long.';
			$u->date = 'This is not a well formatted date.';
			$u->favourite_int = -2;
			$u->favourite_float = -3.14159;
			$u->biography = 'Thîz s†rîñg contains a sentence, the contents of which has been intentionally designed in order that the total length of the string, including a final punctuation mark in the form of a period, is no more or less than two hundred and fifty five characters.';
			$u->homepage = 'This is not a valid url';
			$res = $u->insert();
			$this->wrapAssert(assertEquals, [$res, $res_exp], 'Insert validation: value restriction checking');

			$t = new Thing;
			$t->thingname = 'non-alphabetical';
			$res = $t->insert();
			$res_exp = new TMResult(TMResult::InvalidData, null, ['thingname' => ValidationError::InvalidValue]);
			$this->wrapAssert(assertEquals, [$res, $res_exp], 'Insert validation: restriction checking (alphabetical)');
		}
	}
