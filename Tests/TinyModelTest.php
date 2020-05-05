<?	
	/*
	 * TinyModelTest.php
	 *
	 * Unit testing for TinyModel
	 * Added 6/11/2014 by Ben Hallstein
	 *
	 */
	
	require_once(__DIR__ . '/Model.php');
	require_once(__DIR__ . '/TM_Generic_DB_TestCase.php');
	require_once(__DIR__ . '/validation_test_data.php');
	
	
	class TinyModelTest extends TM_Generic_DB_TestCase {
		
		protected function getDataSet() {
			return $this->createMySQLXMLDataSet('initial_test_data.xml');
		}
		
		public static function setUpBeforeClass() {
			
		}
		
		protected function wrapAssert($method, $args, $str) {
			echo "\n - ", $str;		// NB Output is cached by PHPUnit
			call_user_method_array($method, $this, $args);
		}
		
		protected function setUp() {
			TinyModel::setConnection(self::getPDO());
			parent::setUp();
		}
		
		protected function tearDown() {
			echo "\n\n";
			parent::tearDown();
		}
		
		// Test DB setup
		public function testDataBaseConnection() {
			$queryTable = $this->getConnection()->createQueryTable('users', 'select * from users');
			// This seems to be equivalent:
			//   $ds = $this->getConnection()->createDataSet(['users']);
			//   $queryTable = $ds->getTable('users');
			$expectedTable = $this->getDataSet()->getTable('users');
			$this->wrapAssert(assertTablesEqual, [$expectedTable, $queryTable], 'Database loaded');
			$this->assertTablesEqual($expectedTable, $queryTable, 'Database loaded correctly');
	    }
		
		public function test_Fetch() {
			// Test fetch checks it has at least 1 condition
			$res = User::fetch([ ]);
			$res_exp = new TMResult(TMResult::InvalidConditions, null, null);
			$this->wrapAssert(assertEquals, [$res, $res_exp], 'Fetch: checks conditions - fails if none');
			
			// Test fetch returns expected result with and-conditions
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
			$this->wrapAssert(assertEquals, [$fetched_userids, [ 2, 3 ]], 'Fetch: condition conjunctions: _Or');
			
			// Nested conditions
			$res = User::fetch([
				new Condition('userid', 3, Condition::Equals, Condition::_Or),
				[
					new Condition('userid', 2, Condition::LessThan),
					new Condition('favourite_int', 7)
				]
			]);
			$fetched_userids = [ ];
			foreach ($res->result as $res) $fetched_userids []= $res->userid;
			$this->wrapAssert(assertEquals, [$fetched_userids, [ 1, 3 ]], 'Fetch: nested conditions');
			
			// Test single join
			$res = User::fetch(
				new Condition('userid', 1),
				new Join(Favourite, 'userid')
			);
			$fetched_favids = [ ];
			foreach ($res->result[0]->favourites as $f) $fetched_favids []= $f->favouriteid;
			$this->wrapAssert(assertEquals, [$fetched_favids, [ 1, 2 ]], 'Fetch: single join');
			
			// Test nested join
			$res = User::fetch(
				new Condition('userid', 2),
				new Join(Favourite, 'userid', new Join(Thing, ['thing_id' => 'thingid']))
			);
			$thingname = $res->result[0]->favourites[0]->things[0]->thingname;
			$this->wrapAssert(assertEquals, [$thingname, 'Octopus'], 'Fetch: nested join');
		}
		
		public function test_Update() {
			// Test update checks it has at least 1 condition
			$res = User::update(['username'=>'something'], [ ]);
			$res_exp = new TMResult(TMResult::InvalidConditions, null, null);
			$this->wrapAssert(assertEquals, [$res, $res_exp], 'Update: checks conditions - fails if none');
			
			// Test updates that fail due to bad conditions do not alter DB state
			$res = User::update(['username' => 'benben'], new Condition('userid', '1'));
			$queryTable = $this->getConnection()->createQueryTable('users', 'select * from users');
			$expectedTable = $this->getDataSet()->getTable('users');
			$this->wrapAssert(assertTablesEqual, [$expectedTable, $queryTable], 'Update: failures from conditions do not affect DB');
			
			// Test updates that fail due to bad updates do not alter DB state
			$res = User::update(['username' => 'ben_nonalpanumeric'], new Condition('userid', 1));
			$queryTable = $this->getConnection()->createQueryTable('users', 'select * from users');
			$this->wrapAssert(assertTablesEqual, [$expectedTable, $queryTable], 'Update: failures from updates array do not affect DB');
			
			// Test updates that return success take effect in the DB
			$res = User::update(['username' => 'bhallstein'], new Condition('userid', 1));
			$queryTable = $this->getConnection()->createQueryTable('users', 'select * from users');			
			$expectedTable = $this->createMySQLXMLDataSet('test_data_after_update.xml')->getTable('users');
			$this->wrapAssert(assertTablesEqual, [$expectedTable, $queryTable], 'Update: expected effect when successful');
		}
		
		public function test_Insert() {
			// Test inserts that fail due to bad values do not alter DB
			$t = new Thing;
			$t->thingname = 'Honey Badger';
			$res = $t->insert();
			$queryTable = $this->getConnection()->createQueryTable('things', 'select * from things');
			$expectedTable = $this->getDataSet()->getTable('things');
			$this->wrapAssert(assertTablesEqual, [$expectedTable, $queryTable], 'Insert: failures from bad values do not affect DB');
			
			// Test successful inserts
			$t = new Thing;
			$t->thingname = 'Stoat';
			$res = $t->insert();
			$queryTable = $this->getConnection()->createQueryTable('things', 'select * from things');
			$expectedTable = $this->createMySQLXMLDataSet('test_data_after_insert.xml')->getTable('things');
			$this->wrapAssert(assertTablesEqual, [$expectedTable, $queryTable], 'Insert: expected effect when successful');
		}
		
		public function test_ConditionValidation() {
			// Test condition field/value validation

			$conditionsTestData = getValidationTestData('condition');
			foreach ($conditionsTestData as $item) {
				$res = $item['class']::fetch(new Condition($item['field'],
				                             $item['value']));
				$this->wrapAssert(assertEquals,
				                  [ $res, $item['res_exp'] ],
								  'Condition validation: ' . $item['description']);
			}
		}
		
		public function test_UpdateValidation() {
			$updatesTestData = getValidationTestData('update');
			$passingConditions = [
				'User' => new Condition('userid', 7),
				'Thing' => new Condition('thingid', 7)
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
				'username' => ValidationError::InvalidValue,
				'email' => ValidationError::InvalidValue,
				'password' => ValidationError::InvalidValue,
				'date' => ValidationError::InvalidValue,
				'favourite_int' => ValidationError::InvalidValue,
				'favourite_float' => ValidationError::InvalidValue,
				'biography' => ValidationError::InvalidValue,
				'homepage' => ValidationError::InvalidValue
			]);
			$this->wrapAssert(assertEquals, [$res, $res_exp], 'Insert validation: type checking');
			
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
			$this->wrapAssert(assertEquals, [$res, $res_exp], 'Insert validation: restriction checking');
			
			$t = new Thing;
			$t->thingname = 'non-alphabetical';
			$res = $t->insert();
			$res_exp = new TMResult(TMResult::InvalidData, null, ['thingname' => ValidationError::InvalidValue]);
			$this->wrapAssert(assertEquals, [$res, $res_exp], 'Insert validation: restriction checking (alphabetical)');
		}
	}
