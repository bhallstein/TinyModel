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
	require_once(__DIR__ . '/../Diff/helpers.php');

	class TinyModelDiffHelpersTest extends TM_Generic_DB_TestCase {

		protected function getDataSet() {
			return $this->createMySQLXMLDataSet('initial_test_data.xml');
		}

		public static function setUpBeforeClass() {

		}

		protected function setUp() {
			// TinyModel::setConnection(self::getPDO());
			parent::setUp();
		}

		protected function tearDown() {
			echo "\n\n";
			parent::tearDown();
		}

		// Test DB setup
		public function testHelper_typefns() {
			$test_sql_str = 'username varchar(20) not null';

			$coltype = Helpers::type_from_mysql_type_string($test_sql_str);
			$this->wrapAssert(assertEquals, [$coltype, 'username'], 'Diff/Helpers: type_from_mysql_type_string()');

			$collength = Helpers::char_length_from_mysql_type_string($test_sql_str);
			$this->wrapAssert(assertEquals, [$collength, 20], 'Diff/Helpers: char_length_from_mysql_type_string()');
	   }

		public function testHelper_tblDescr() {
			$result = Helpers::get_table_descriptions_for_DB(self::getPDO());

			// 3 entries returned
			$this->wrapAssert(assertEquals, [count($result), 3], 'Diff/Helpers: get_table_descriptions_for_DB(): 3 tables');
			$this->wrapAssert(assertEquals, [is_array($result['favourites']), true], 'Diff/Helpers: get_table_descriptions_for_DB(): favourites exists');
			$this->wrapAssert(assertEquals, [is_array($result['things']), true], 'Diff/Helpers: get_table_descriptions_for_DB(): things exists');
			$this->wrapAssert(assertEquals, [is_array($result['users']), true], 'Diff/Helpers: get_table_descriptions_for_DB(): users exists');

			$this->wrapAssert(assertEquals,
			                  [
										 $result['favourites']['favouriteid'],
										 ['type' => 'int',
										 'notnull' => true,
										 'unsigned' => true]
									 ],
									 'Diff/Helpers: get_table_descriptions_for_DB(): id columns');
			$this->wrapAssert(assertEquals,
			                  [
										 $result['users']['favourite_int'],
										 ['type' => 'int',
										 'notnull' => true,
										 'unsigned' => false]
									 ],
									 'Diff/Helpers: get_table_descriptions_for_DB(): int columns');
			$this->wrapAssert(assertEquals,
			                  [
										 $result['users']['favourite_float'],
										 ['type' => 'float',
										 'notnull' => true]
									 ],
									 'Diff/Helpers: get_table_descriptions_for_DB(): float columns');
			$this->wrapAssert(assertEquals,
			                  [
										 $result['users']['homepage'],
										 ['type' => 'varchar',
										 'notnull' => false,
										 'length' => 100]
									 ],
									 'Diff/Helpers: get_table_descriptions_for_DB(): varchar columns');
		}

		public function testHelper_sqlDescr() {
			$c = new Column('id');

			$descr = Helpers::get_SQL_description($c, 'xid', false);
			$this->wrapAssert(assertEquals,
									[$descr,
									 '`xid` int unsigned auto_increment not null'],
									'Diff/Helpers: get_SQL_description(): id col');

			$descr = Helpers::get_SQL_description($c, 'xid', true);
			$this->wrapAssert(assertEquals,
									[$descr,
									 '`xid` int unsigned auto_increment not null primary key'],
									'Diff/Helpers: get_SQL_description(): id col (adding)');

			$c = new Column('int');
			$descr = Helpers::get_SQL_description($c, 'xid', true);
			$this->wrapAssert(assertEquals,
									[$descr,
									 '`xid` int null'],
									'Diff/Helpers: get_SQL_description(): int');

			$c = new Column('int positive');
			$descr = Helpers::get_SQL_description($c, 'xid', true);
			$this->wrapAssert(assertEquals,
									[$descr,
									 '`xid` int unsigned null'],
									'Diff/Helpers: get_SQL_description(): int +ve');

			$c = new Column('float');
			$descr = Helpers::get_SQL_description($c, 'xid', true);
			$this->wrapAssert(assertEquals,
									[$descr,
									 '`xid` float null'],
									'Diff/Helpers: get_SQL_description(): int +ve');

			$c = new Column('char');
			$descr = Helpers::get_SQL_description($c, 'xid', true);
			$this->wrapAssert(assertEquals,
									[$descr,
									 '`xid` varchar null'],
									'Diff/Helpers: get_SQL_description(): char/varchar');

			$c = new Column('char maxlength=20');
			$descr = Helpers::get_SQL_description($c, 'xid', true);
			$this->wrapAssert(assertEquals,
									[$descr,
									 '`xid` varchar(20) null'],
									'Diff/Helpers: get_SQL_description(): char/varchar - maxlength');

			$c = new Column('text');
			$descr = Helpers::get_SQL_description($c, 'xid', true);
			$this->wrapAssert(assertEquals,
									[$descr,
									 '`xid` text null'],
									'Diff/Helpers: get_SQL_description(): text');

			$c = new Column('timestamp');
			$descr = Helpers::get_SQL_description($c, 'xid', true);
			$this->wrapAssert(assertEquals,
									[$descr,
									 '`xid` timestamp null'],
									'Diff/Helpers: get_SQL_description(): timestamp');

			$c = new Column('int notnull');
			$descr = Helpers::get_SQL_description($c, 'xid', true);
			$this->wrapAssert(assertEquals,
									[$descr,
									 '`xid` int not null'],
									'Diff/Helpers: get_SQL_description(): notnull');
		}
	}
