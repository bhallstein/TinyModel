<?

	/*
	 * TinyModel - a sort of model type thing
	 *
	 * TinyModel allows objects representing the DB model to be easily defined by subclassing.
	 * Functionality is exposed through the methods `fetch`, `insert`, and `update`.
	 * 
	 * On success, `fetch` generates & returns nested objects from the data retrieved.
	 *
	 * Copyright (c) 2010 - Ben Hallstein - ben.am
	 * Published under the MIT license - http://opensource.org/licenses/MIT
	 *
	 */
	
	
	// Column definition string: 'type [restrictions]'
	//   type: int float varchar text timestamp
	// 	 restrictions: alphabetical alphanumeric email url positive notnull maxlength=N
	
	class Column {
		public function __construct($definition_string) {
			// Process definition string and set Column properties
			$this->type = strtok($definition_string, ' ');
			
			while (($attrib = strtok(' ')) !== false) {
				if ($attrib == 'alphabetical')      $this->rAlphabetical = true;
				else if ($attrib == 'alphanumeric') $this->rAlphanumeric = true;
				else if ($attrib == 'email')        $this->rEmail = true;
				else if ($attrib == 'url')          $this->rURL = true;
				else if ($attrib == 'positive')     $this->rPositiveNumber = true;
				else if ($attrib == 'notnull')      $this->rNotNull = true;
				else if (strpos($attrib, 'maxlength') !== false) {
					$this->maxLength = explode('=', $attrib);
					$this->maxLength = $this->maxLength[1];
				}
			}
			
			$this->definition_string = $definition_string;
		}
		
		public function validate($val) {
			if ($val === null && $this->rNotNull) return false;
			if ($this->type == 'int')	{
				if ($this->rPositiveNumber) return is_int($val) && $val >= 0;
				return is_int($val);
			}
			else if ($this->type == 'float') {
				if ($this->rPositiveNumber) return is_float($val) && $val >= 0;
				return is_float($val);
			}
			else if ($this->type == 'varchar' || $this->type == 'text') {
				if (!is_string($val)) return false;
				$pass = true;
				if ($this->rAlphabet)     $pass = mb_ereg_match('^[a-zA-Z]+$', $val);
				else if ($this->rAlphanumeric) $pass = mb_ereg_match('^[a-zA-Z0-9]+$', $val);
				else if ($this->rEmail) $pass = filter_var(mb_ereg_replace('[^\x00-\x7f]', '-', $val), FILTER_VALIDATE_EMAIL);
				else if ($this->rURL)   $pass = filter_var(mb_ereg_replace('[^\x00-\x7f]', '-', $val), FILTER_VALIDATE_URL);
				
				if ($this->maxLength)
					$pass = $pass && mb_strlen($val) <= $this->maxLength;
				
				return $pass;
			}
			else if ($this->type == 'timestamp') {
				return mb_ereg('^\d{2,4}.\d\d.\d\d \d\d.\d\d.\d\d$', $val);
			}
			return false;
		}
	}
	
	
	class Join {
		public function __construct($cl, $co, $j = array()) {
			$this->class = $cl;
			$this->cols  = is_array($co) ? $co : array($co);
			$this->joins = is_array($j) ? $j : array($j);
		}
	}
	
	
	class Condition {
		private $column;
		private $test;
		private $value;
 		public $conjoinment;
		
		private static $testStrings;
		
		const Equals              = 0;
		const NotEquals           = 1;
		const LessThan            = 2;
		const LessThanOrEquals    = 3;
		const GreaterThan         = 4;
		const GreaterThanOrEquals = 5;
		
		const _And = 20;
		const _Or = 21;
		
		const Recent = 50;
		
		public function __construct($col, $val, $test = self::Equals, $conjoinment = self::_And) {
			$this->column = $col;
			$this->value = $val;
			$this->test = $test;
			$this->conjoinment = ($conjoinment == self::_And ? 'and' : 'or');
		}
		
		public function toStr($prefix = '') {
			// Return the condition as a string
			if ($prefix !== '') $prefix .= '.';
			
			if ($this->value == self::Recent) {
				// period should be stored in test
				$s = 'unix_timestamp(now()) - unix_timestamp(' .
						escape_string($this->column) . ') < ' . (int)$this->test;
			}
			else if ($this->column === 'password') {
				$s = "{$prefix}password = md5(sha(concat(salt, '" .
						escape_string($this->value) .
						"')))";
			}
			else {
				$s = $prefix . mysql_real_escape_string($this->column) .
					' ' . self::$testStrings[$this->test] . ' ' .
					(
						(is_int($this->val) or is_float($this->val)) ? $this->val :
						("'" . mysql_real_escape_string($this->value) . "'")
					);
			}
			
			return $s;
		}
		
		public static function _init() {
			self::$testStrings = array(
				self::Equals              => '=',
				self::NotEquals           => '!=',
				self::LessThan            => '<',
				self::LessThanOrEquals    => '<=',
				self::GreaterThan         => '>',
				self::GreaterThanOrEquals => '>='
			);
		}
	}
	Condition::_init();
	
	
	
	class TinyModel {
		
		private static $tableNames;		// Array of names of subclass tables: {'User' => 'users'}
		private static $tableCols;		// Table columns {'User' => A}
										//  - A is an array of Column objects, which have:
										//    - a type
										//    - a set of restrictions

		// You must access the table name and columns through the getters, which perform reflection,
		// returning the name/columns appropriate to the calling subclass (!)
		
		static function &getTableCols() {
			if (self::$tableCols === null)
				self::$tableCols = array();
			if (self::$tableCols[$subclass_name = get_called_class()] === null) {
				// Get the column definitions from the user subclass
				$rc = new ReflectionClass($subclass_name);
				$columns = $rc->getConstants();			// -> {constant_name => constant_value}
				
				// Create static array of Column objects
				self::$tableCols[$subclass_name] = array();
				foreach ($columns as $col_name => $col_definition_string) {
					self::$tableCols[$subclass_name][$col_name] = new Column($col_definition_string);
				}
			}
			return self::$tableCols[$subclass_name];
		}
	  	static function &getTableName() {
			if (self::$tableNames === null)
				self::$tableNames = array();
			if (self::$tableNames[$subclass_name = get_called_class()] === null) {
				$class = strtolower($subclass_name);
				$c = substr($class, -1);
				$c2 = substr($class, -2, -1);
				if ($c == 'y' && !preg_match('/[aeiou]+/', $c2))
					self::$tableNames[$subclass_name] = substr($class, 0, -1) . 'ies';
				else if ($c == 'h')
					self::$tableNames[$subclass_name] = $class . 'es';
				else self::$tableNames[$subclass_name] = $class . 's';
			}
			return self::$tableNames[$subclass_name];
		}
		
		
		// objFromRow:
		//  - take a row of a table containing one of this class:
		//    - the row will has a prefix, eg a_users
		//    - for each column defined in this class, attempt to fetch it from row
		//    - return object or null if nothing found
		static function objFromRow($row, $prefix) {
			$cols = array_keys(self::getTableCols());
			$new_obj = new static();
			$n_imported_columns = 0;
			foreach($cols as $col) {
				$new_obj->$col = $row[$prefix . '_' . $col];
				if ($new_obj->$col !== null)
					$n_imported_columns++;
			}
			return ($n_imported_columns == 0) ? null : $new_obj;
		}
		
		
		// conditionStr:
		//  - return the conditions formatted into a string for use in an SQL statement
		//  - accept either:
		//    - a Condition object
		//    - a (nested) array of Condition objects
		
		static function conditionStr($c, $prefix = '') {
			$s = '';

			if ($c instanceof Condition) {
				$s .= 'where ';
				$s .= $c->toStr($prefix);
			}

			else if (is_array($c) && count($c)) {
				echo "iterating...\n";
				// Iterate recursively over the array of conditions
				$getConditionStringForArray = function($a) use(&$getConditionStringForArray) {
					$conjoinment = null;
					$s = '';
					foreach ($a as $c) {
						if ($conjoinment) $s .= $conjoinment . ' ';
						if (is_array($c))
							$s .= '(' . $getConditionStringForArray($c) . ') ';
						else if ($c instanceof Condition) {
							$s .= $c->toStr($prefix) . ' ';
							$conjoinment = $c->conjoinment;
						}
					}
					return $s;
				};
				$s .= $getConditionStringForArray($c);
			}
			
			return $s;
		}
		
		
		// Validate
		// 	- take an array of fields: {column_name => value}, ... 
		//  - check that the supplied columns exist, and the values pass validation
		//  - returns an array of errors: {column_name => error}, ...
		
		static function validate(&$fields) {
			$class_columns = &self::getTableCols();
			$errors = array();
			
			foreach($fields as $columnName => $value) {
				$col = $class_columns[$columnName];
				
				if (!isset($col))
					$errors[$columnName] = 'Unknown column name';
					
				else if ($value == null)
					$errors[$columnName] = 'Null value supplied';
				
				else {
					$validated = $col->validate($value);
					if (!$validated)
						$errors[$columnName] = 'Illegal ' . gettype($value) . ' value \'' . $value .
							'\' (column definition: ' . $col->definition_string . ')';
				}
			}
			
			return $errors;
		}
		
		
		private function wouldDifferFromRow(&$row, $prefix) {
			$id_column = array_shift(array_keys(self::getTableCols()));
			return $row["{$prefix}_{$id_column}"] != $this->$id_column;
		}
		
		function appendToArray($arrname, $val) {
			if (!isset($this->$arrname) or !is_array($this->$arrname))
				$this->$arrname = array();
			array_push($this->$arrname, $val);
		}
		
		
		// Fetch: fetch from the table with the given conditions & joins
		// 	- returns false on db error
		//  - returns an array reresenting the returned object(s) on success
		
		static function fetch($conditions = array(), $joins = array(), $debug = false) {
			$table = &self::getTableName();
			$cols  = &self::getTableCols();
			
			foreach($cols as $colname => $coltype) $q []= "a.$colname as a_{$colname}";
			$query_select_columns = array( implode(', ', $q) );
			
			$query_from_tables = array();
			$query_joins       = array();
			$i = 0;
			
			$add_join = function(&$join, &$parent = null)
				use (&$i, &$query_select_columns, &$query_joins, &$add_join) {
				$join->prefix = $prefix = chr($i++ + 98);
				$join->parent = $parent;
				$join->stalled = false;
				$parent_prefix = $parent ? $parent->prefix : 'a';
				
				$cla = $join->class;
				$table = &$cla::getTableName();
				$cols  = &$cla::getTableCols();
				
				$q = array();
				foreach ($cols as $colname => $coltype) {
					$s = $coltype == 'timestamp' ? "unix_timestamp($prefix.$colname)" : "$prefix.$colname";
					$q []= "$s as {$prefix}_{$colname}";
				}
				$query_select_columns []= implode(', ', $q);
				
				$q = array();
				foreach ($join->cols as $jcol)
					$q []= "$parent_prefix.$jcol = $prefix.$jcol";
				$query_joins []= "left join $table as $prefix on " . implode(' and ', $q);
				
				foreach($join->joins as &$j)
					$add_join($j, $join);
			};
			
			foreach($joins as &$j) $add_join($j);
			$query_select_columns = implode(', ', $query_select_columns);
			$query_joins = implode(' ', $query_joins);
			
			$cond = self::conditionStr($conditions, 'a');
			if ($cond === '') return false;

			$q = "select $query_select_columns from $table as a $query_joins $cond";
			if ($debug) var_dump($q);
			$r = mysql_query($q);
			if (!$r) return false;			

			$base_objs = array();
			$increments_from_stalled_joins = array();
			$row = mysql_fetch_assoc($r);
			
			$destall = function(&$join) use (&$increments_from_stalled_joins, &$destall) {
				foreach($join->joins as &$_j) $destall($_j);
				$join->stalled = false;
				unset($increments_from_stalled_joins[$join->prefix]);
			};
			
			$recursively_add_row = function(&$j, &$parent_obj) use (&$row, &$increments_from_stalled_joins, &$destall, &$recursively_add_row) {
				if ($j->stalled) return;
				
				$cla = $j->class;
				$table = $cla::getTableName();
				$prefix = $j->prefix;
				
				$id_column = array_shift(array_keys($cla::getTableCols()));
				if ($row["{$prefix}_$id_column"] == null) {
					$j->stalled = true;
					return;
				}
				
				if (isset($parent_obj->$table)) $n = count($parent_array = $parent_obj->$table);
				else $n = 0;
				
				// Check for cyclic repetition
				if ($n > 1 and !$parent_array[0]->wouldDifferFromRow($row, $prefix)) {
					$j->stalled = true;
					$increments_from_stalled_joins[$prefix] = $n;
					return;
				}
				
				// If new, add, destall descendants, & recurse
				if (!$n or $parent_array[$n - 1]->wouldDifferFromRow($row, $prefix)) {
					$obj = $cla::objFromRow($row, $prefix);
					if (!$obj) ; // Bad
					
					$parent_obj->appendToArray($table, $obj);
					foreach ($j->joins as &$_j) $destall($_j);
					foreach ($j->joins as &$_j) $recursively_add_row($_j, $obj);
				}
			};
			
			while ($row) {
				$n = count($base_objs);
				if (!$n or $base_objs[$n - 1]->wouldDifferFromRow($row, 'a')) {
					if (!$obj = self::objFromRow($row, 'a'))
						break;
					$base_objs []= &$obj;
					foreach ($joins as &$_j) $destall($_j);
				}
				else
					$obj = &$base_objs[$n - 1];
				
				foreach($joins as &$_j)
					$recursively_add_row($_j, $obj);
				$inc = 1;
				foreach($increments_from_stalled_joins as $x) $inc *= $x;
				for ($i=0; $i < $inc; $i++)
					$row = mysql_fetch_assoc($r);
				unset($obj);
			}
			
			return $base_objs;
		}
		
		
		// Update: update a value in the table
		//  - returns an array of errors if the updated values fail validation
		// 	- returns false on db error
		//  - returns the number of altered rows on success
		
		static function update($updates, $conditions) {
			
			if (!is_array($conditions) && ! $conditions instanceof Condition)
				return false;
				
			$t_name = &self::getTableName();
			
			if ($errors = self::validate($updates))		// [col => val, ...]
				return $errors;
			
			foreach($updates as $c => $v)
				$set []= "$c = " . ((is_int($v) or is_float($v)) ? $v : "'" . mysql_real_escape_string($v) . "'");
			$set = 'set ' . implode(', ', $set);
			
			$cond = self::conditionStr($conditions);
			if ($cond === '') return false;
				// conditionStr() may return an empty string if an array of non-Condition objects
				// was passed in.
			
			$q = "update $t_name $set $cond";
			$r = mysql_query($q);
			return ($r ? mysql_affected_rows() : false);
		}
		
		
		// Insert: insert this object into its table
		// 	- if illegal values encoutered, returns an array of errors
		// 	- on db error, returns false
		//	- otherwise, returns the insert id
		
		function insert() {
			$t_name = &self::getTableName();
			$fields = get_object_vars($this);
			
			// Check fields are legal
			$errors = self::validate($fields);
			if (count($errors)) return $errors;
			
			$cols = $vals = array();
			
			foreach ($fields as $c => $v) {
				if ($v === null) continue;
				$cols []= $c;
				if (is_int($v) or is_float($v)) $vals []= $v;
				else							$vals []= "'" . mysql_real_escape_string($v) . "'";
			}
			
			$cols = implode(', ', $cols);
			$vals = implode(', ', $vals);
			
			$q = "insert into $t_name
					( $cols )
					values ( $vals )";
			
			if (!$r = mysql_query($q))
				return false;
			else
				return mysql_insert_id();
		}
		
	}

?>
