<?
	/*
	 * TM_Generic_DB_TestCase.php
	 *
	 * Define an abstract base class to manage a connection
	 * Added 6/11/2014 by Ben Hallstein
	 *
	 */

	abstract class TM_Generic_DB_TestCase extends PHPUnit_Extensions_Database_TestCase
	{
	    static protected $pdo = null;   // Global connection obj
	    private $conn = null;           // Per-class connection wrapper obj
		
	    final public function getConnection() {
	        if ($this->conn === null)
	            $this->conn = $this->createDefaultDBConnection(self::getPDO(), $GLOBALS['DB_DBNAME']);
			return $this->conn;
		}
		
		protected static function getPDO() {
			if (self::$pdo === null)
				self::$pdo = new PDO($GLOBALS['DB_DSN'], $GLOBALS['DB_USER'], $GLOBALS['DB_PASSWD']);
			return self::$pdo;
		}
	}
	
	
?>
