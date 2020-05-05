<?
	/*
	 * TM_Generic_DB_TestCase.php
	 *
	 * Define an abstract base class to manage a connection
	 * Added 6/11/2014 by Ben Hallstein
	 *
	 */

	require_once('testdata/test_data_initial.php');

	abstract class TM_Generic_DB_TestCase extends PHPUnit_Framework_TestCase
	{
		static protected $pdo = null;   // Global connection obj
		private $conn = null;           // Per-class connection wrapper obj

		protected static function getPDO() {
			if (self::$pdo === null) {
				self::$pdo = new PDO('mysql:dbname=tinymodel_test;host=127.0.0.1', 'tm_testuser', 'pwd');
			}
			return self::$pdo;
		}

		protected function wrapAssert($method, $args, $str) {
			echo "\n - ", $str;
			call_user_method_array($method, $this, $args);
		}

		protected function setUp() {
			$pdo = self::getPDO();
			$this->tables_drop();
			$this->tables_create();
			$this->tables_fill();
		}

		protected function tearDown() {
			$this->tables_drop();
		}

		private function tables_drop() {
			global $tmtest_initial_table_data;
			foreach ($tmtest_initial_table_data as $tbl => $tbldata) {
				$r = self::getPDO()->exec("drop table if exists `$tbl`");
				if ($r === false) {
					echo "TM_Generic_DB_TestCase error in test_tables_drop():\n";
					var_dump(self::getPDO()->errorInfo());
				}
			}
		}

		private function tables_create() {
			global $tmtest_initial_table_creation_queries;
			foreach ($tmtest_initial_table_creation_queries as $q) {
				$r = self::getPDO()->exec($q);
				if ($r === false) {
					echo "TM_Generic_DB_TestCase error in test_tables_create():\n";
					var_dump(self::getPDO()->errorInfo());
				}
			}
		}

		private function tables_fill() {
			global $tmtest_initial_table_data;
			foreach ($tmtest_initial_table_data as $tbl => $tbldata) {
				foreach ($tbldata as $row) {
					$cols   = array_keys($row);
					$values = array_values($row);
					$cols   = array_map(function ($x) { return "`$x`"; }, $cols);
					$values = array_map(function ($x) { return "'$x'"; }, $values);

					$q = "insert into `$tbl` " .
					        '(' . implode(', ', $cols) . ') ' .
					        'values (' . implode(', ', $values) . ')';
					$r = self::getPDO()->exec($q);
					if ($r === false) {
						echo "TM_Generic_DB_TestCase error in test_tables_fill():\n";
						var_dump(self::getPDO()->errorInfo());
					}
				}
			}
		}
	}
