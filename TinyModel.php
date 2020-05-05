<?

	/*
	 * TinyModel - a sort of model type thing
	 *
	 * TinyModel is a superclass that lets you easily define the Model layer of a web app, and
	 * handles the translation of database tables into user-friendly nested objects, and vice versa.
	 *
	 * Each subclass you define represents a table in your DB schema. The columns of the table
	 * are defined by adding a few simple class constants to the subclass, defining the name
	 * and type of the column, and optional restrictions on what may be inserted in it.
	 *
	 * Functionality is exposed through the methods `fetch`, `insert`, and `update`.
	 * 
	 * The `fetch` method traverses the data retrieved, returning a nested array of objects that
	 * is very easy to use in web application code.
	 *
	 * Copyright (c) 2010 - Ben Hallstein - ben.am
	 * Published under the MIT license - http://opensource.org/licenses/MIT
	 *
	 */
	
	
	// Column definition string: 'type [restrictions]'
	//   type: int float char varchar text timestamp
	//   restrictions: alphabetical alphanumeric email url positive notnull maxlength=N
	
	// Less stringly-typed way of doing this: bit field/array of TM-defined values.
	//  - However, this results in unacceptable verbosity user-side. Hence, strings.
	
	class Column {
		public function __construct($definition_string) {
			// Process definition string and set Column properties
			$this->type = strtok($definition_string, ' ');
			if ($this->type == 'char') $this->type = 'varchar';
			
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
			if ($val === null) return !$this->rNotNull;
			if ($this->type == 'int')	{
				if ($this->rPositiveNumber) return is_int($val) && $val >= 0;
				return is_int($val);
			}
			else if ($this->type == 'float') {
				if (is_int($val)) $val = (float) $val;
				if ($this->rPositiveNumber) return is_float($val) && $val >= 0;
				return is_float($val);
			}
			else if ($this->type == 'varchar' || $this->type == 'text') {
				if (!is_string($val)) return false;
				$pass = true;
				if ($this->rAlphabetical)      $pass = mb_ereg_match('^[a-zA-Z]+$', $val);
				else if ($this->rAlphanumeric) $pass = mb_ereg_match('^[a-zA-Z0-9]+$', $val);
				else if ($this->rEmail) $pass = filter_var(mb_ereg_replace('[^\x00-\x7f]', '-', $val), FILTER_VALIDATE_EMAIL);
				else if ($this->rURL)   $pass = filter_var(mb_ereg_replace('[^\x00-\x7f]', '-', $val), FILTER_VALIDATE_URL);
				
				if ($this->maxLength) {
					$pass_length = (($this->type == 'varchar' ? mb_strlen($val, 'utf8') : mb_strlen($val, 'latin1')) <= $this->maxLength);
					$pass = $pass && $pass_length;
				}
				
				return $pass;
			}
			else if ($this->type == 'timestamp') {
				return (is_string($val) && mb_ereg('^\d{2,4}.\d\d.\d\d \d\d.\d\d.\d\d$', $val));
			}
			return false;
		}
	}
	
	
	class Join {
		public function __construct($cl, $co, $j = [ ]) {
			$this->class = $cl;
			$this->cols  = (is_array($co) ? $co : [$co]);
			$this->joins = (is_array($j) ? $j : [$j]);
		}
	}
	
	
	class ValidationError {
		const NonexistentColumn = 0;
		const InvalidValue      = 4;
		const UnknownObject     = 8;
		private function __construct() { }
	}
	
	
	class Condition {
		public $column;
		public $test;
		public $value;
 		public $conjunction;
		
		private static $testStrings;
		
		const Equals              = 0;
		const NotEquals           = 1;
		const LessThan            = 2;
		const LessThanOrEquals    = 3;
		const GreaterThan         = 4;
		const GreaterThanOrEquals = 5;
		const Recent              = 6;
		
		const _And = 20;
		const _Or = 21;
		
		public function __construct($col, $val, $test = self::Equals, $conjunction = self::_And) {
			$this->column = $col;
			$this->value = $val;
			$this->test = $test;
			$this->conjunction = $conjunction;
		}
		
		public function testString() {
			$t = self::$testStrings[$this->test];
			return (isset($t) ? $t : '=');
		}
		
		public function conjString() {
			return ($this->conjunction == self::_And ? 'and' : 'or');
		}
		
		public static function _init() {
			self::$testStrings = [
				self::Equals              => '=',
				self::NotEquals           => '!=',
				self::LessThan            => '<',
				self::LessThanOrEquals    => '<=',
				self::GreaterThan         => '>',
				self::GreaterThanOrEquals => '>='
			];
		}
	}
	Condition::_init();
	
	
	class TMResult {
		public $status;
		public $result;
		public $errors;
		
		public function __construct($status, $result = null, $errors = null) {
			$this->status = $status;
			$this->result = $result;
			$this->errors = $errors;
		}
		
		const Success = 0;
		const InvalidData = 1;
		const InvalidConditions = 2;
		const InternalError = 3;
	}
	
	
	class TinyModel {
		
		protected static $pdo;      // The PDO object to be used for a DB connection
		static function setConnection($pdo) {
			self::$pdo = $pdo;
		}
		
		protected static $bind_params;				// When building a prepared statement, we store params here
		protected function bindBindParams($st) {	// :P
			for ($i = 0, $n = count(self::$bind_params); $i < $n; ++$i)
				$st->bindParam($i+1, self::$bind_params[$i]);
		}
		
		private static $tableNames;		// Array of names of subclass tables: {'User' => 'users'}
		private static $tableCols;		// Table columns {'User' => A}
										//  - A is an array of Column objects, which have:
										//    - a type
										//    - a set of restrictions

		// Table names and columns must be accessed using the getters, which perform reflection,
		// returning the name/columns appropriate to the calling subclass (!)
		
		protected static function &getTableCols() {
			if (self::$tableCols === null)
				self::$tableCols = [ ];
			if (self::$tableCols[$subclass_name = get_called_class()] === null) {
				// Get the column definitions from the user subclass
				$rc = new ReflectionClass($subclass_name);
				$columns = $rc->getConstants();			// -> {constant_name => constant_value}
				
				// Create static array of Column objects
				self::$tableCols[$subclass_name] = [ ];
				foreach ($columns as $col_name => $col_definition_string) {
					self::$tableCols[$subclass_name][$col_name] = new Column($col_definition_string);
				}
			}
			return self::$tableCols[$subclass_name];
		}
	  	protected static function &getTableName() {
			if (self::$tableNames === null)
				self::$tableNames = [ ];
			if (self::$tableNames[$subclass_name = get_called_class()] === null) {
				$class = strtolower($subclass_name);
				self::$tableNames[$subclass_name] = self::plural($class);
			}
			return self::$tableNames[$subclass_name];
		}
		
		protected static function plural($s) {
			$c = substr($s, -1);
			$c2 = substr($s, -2, -1);
			$c2_is_vowel = (strpos('aeiou', $c2) !== false);
			if ($c == 'y' && !$c2_is_vowel)
				return substr($s, 0, -1) . 'ies';
			else if (($c == 's' && $c2_is_vowel) || $c == 'h')
				return $s . 'es';
			return $s . 's';
		}
		
		
		// objFromRow:
		//  - take a row of a table containing one of this class:
		//    - the row has a prefix, e.g. a_users
		//    - for each column defined by this class, attempt to fetch it from row
		//    - return an object, or null if nothing found
		
		protected static function objFromRow($row, $prefix) {
			$cols = self::getTableCols();
			$new_obj = new static();
			$n_imported_columns = 0;
			foreach($cols as $colName => $col) {
				$new_obj->$colName = $row[$prefix . '_' . $colName];
				
				// Convert ints & floats
				if ($col->type == 'int')        $new_obj->$colName = (int) $new_obj->$colName;
				else if ($col->type == 'float') $new_obj->$colName = (float) $new_obj->$colName;
				
				if ($new_obj->$colName !== null)
					$n_imported_columns++;
			}
			return ($n_imported_columns == 0) ? null : $new_obj;
		}
		
		
		// conditionStr:
		//  - return conditions formatted into a string for use in an SQL statement
		//  - accept either:
		//    - a Condition object
		//    - a (nested) array of Condition objects
		
		protected static function getSingleCondStr_AndAddBindParam($c, $prefix) {
			// Return the condition as a string
			if ($prefix !== '') $prefix .= '.';
			
			if ($c->test == Condition::Recent)
				$s = "unix_timestamp(now()) - unix_timestamp({$prefix}{$c->column}) < ?";
			else
				$s = "{$prefix}{$c->column} {$c->testString()} ?";
			self::$bind_params []= $c->value;
			
			return $s;
		}
		
		protected static function getCondStr_AndAddBindParams($c, $prefix = '') {
			// Iterate recursively over the array of conditions
			$getStringForConditions = function($x) use(&$getStringForConditions, $prefix) {
				$s = '';
				$conj = null;
				if (is_array($x)) {
					$s .= '(';
					foreach ($x as $y) {
						if ($conj) $s .= " $conj ";
						$s .= $getStringForConditions($y);
						if ($y instanceof Condition) $conj = $y->conjString();
					}
					$s .= ')';
				}
				else if ($x instanceof Condition) {
					$s = self::getSingleCondStr_AndAddBindParam($x, $prefix);
				}
				return $s;
			};
			$s .= $getStringForConditions($c);
			
			return 'where ' . $s;
		}
		
		
		// Validate
		//  - take an array of fields: {column_name => value}, ... 
		//  - check that the supplied columns exist, and the values pass validation
		//  - returns an array of errors: {column_name => error}, ...
		//  - optionally also check completeness - that all notnull columns are present
		
		protected static function validateArray(&$fields, $check_completeness = false) {
			$class_columns = self::getTableCols();		// [ column_name => Column ]
			$errors = [ ];
			
			foreach ($fields as $columnName => $value) {
				$col = $class_columns[$columnName];
				
				if (!isset($col))
					$errors[$columnName] = ValidationError::NonexistentColumn;
				
				else {
					$validated = $col->validate($value);
					if (!$validated)
						$errors[$columnName] = ValidationError::InvalidValue;
				}
			}
			
			if ($check_completeness) {
				foreach ($class_columns as $columnName => $col)
					if ($col->rNotNull && !isset($fields[$columnName]))
						$errors[$columnName] = ValidationError::InvalidValue;
			}
			
			return $errors;
		}
		
		
		protected static function validateConditions(&$c) {
			$errors = [ ];
			$getErrorsForConditions = function(&$x) use(&$getErrorsForConditions, &$errors) {
				if (is_array($x))
					foreach ($x as $c)
						$getErrorsForConditions($c);
				else if ($x instanceof Condition) {
					$class_columns = self::getTableCols();
					$colName = $x->column;
					$col = $class_columns[$colName];
					
					if (!isset($col))
						$errors[$colName] = ValidationError::NonexistentColumn;
					else if (!$col->validate($x->value))
						$errors[$colName] = ValidationError::InvalidValue;
				}
				else
					$errors []= ValidationError::UnknownObject;
			};
			$getErrorsForConditions($c);
			return $errors;
		}
		
		protected function wouldDifferFromRow(&$row, $prefix) {
			$id_column = array_shift(array_keys(self::getTableCols()));	// This is a rather brittle assumption.
			return $row["{$prefix}_{$id_column}"] != $this->$id_column;
		}
		
		protected function appendToArray($arrname, $val) {
			if (!isset($this->$arrname) or !is_array($this->$arrname))
				$this->$arrname = [ ];
			array_push($this->$arrname, $val);
		}
		
		
		// Fetch: fetch rows from the table, using the given conditions & joins
		//  - returns:
		//    - false on db error
		//    - a nested array of object(s) on success
		
		static function fetch($conditions = [ ], $joins = [ ], $debug = false) {
			self::$bind_params = [ ];
			$table = &self::getTableName();
			$cols  = &self::getTableCols();
			
			if ((is_array($conditions) && count($conditions) == 0) ||
				(!is_array($conditions) && !($conditions instanceof Condition))) {
				$res = new TMResult(TMResult::InvalidConditions);
				return $res;
			}
			$errors = self::validateConditions($conditions);
			if (count($errors)) {
				$res = new TMResult(TMResult::InvalidConditions);
				$res->errors = $errors;
				return $res;
			}
			
			foreach($cols as $colname => $col) {
				$s = $col->type == 'timestamp' ? "unix_timestamp(a.$colname)" : "a.$colname";
				$q []= "$s as a_{$colname}";
			}
			$query_select_columns = [ implode(', ', $q) ];
			
			$query_from_tables = [ ];
			$query_joins       = [ ];
			$i = 0;
			
			$add_join = function(&$join, &$parent = null) use (&$i, &$query_select_columns, &$query_joins, &$add_join) {
				$join->prefix = $prefix = chr(98 + $i++);
				$join->parent = $parent;
				$join->stalled = false;
				$parent_prefix = ($parent ? $parent->prefix : 'a');
				
				$cla = $join->class;
				$table = &$cla::getTableName();
				$cols  = &$cla::getTableCols();
				
				$q = [ ];
				foreach ($cols as $colname => $col) {
					$s = ($col->type == 'timestamp' ? "unix_timestamp($prefix.$colname)" : "$prefix.$colname");
					$q []= "$s as {$prefix}_{$colname}";
				}
				$query_select_columns []= implode(', ', $q);
				
				$join_conditions = [ ];
				foreach ($join->cols as $k => $v) {
					if (is_string($k)) $join_conditions []= "$parent_prefix.$k = $prefix.$v";
					else               $join_conditions []= "$parent_prefix.$v = $prefix.$v";
				}
				$query_joins []= "left join $table as $prefix on " . implode(' and ', $join_conditions);
				
				foreach($join->joins as $k => &$j) {
					if (!($j instanceof Join)) unset($join->joins[$k]);
					else                       $add_join($j, $join);
				}
			};
			
			if (!is_array($joins)) $joins = [ $joins ];
			foreach($joins as $k => &$j) {
				if (!($j instanceof Join)) unset($joins[$k]);
				else                       $add_join($j);
			}
				
			$query_select_columns = implode(', ', $query_select_columns);
			$query_joins = implode(' ', $query_joins);
			
			$cond = self::getCondStr_AndAddBindParams($conditions, 'a');
			
			$q = "select $query_select_columns from $table as a $query_joins $cond";
			if ($debug) var_dump($q);
			$st = self::$pdo->prepare($q);
			
			self::bindBindParams($st);
			
			$r = $st->execute();
			if ($r === false) {
				$res = new TMResult(TMResult::InternalError);
				$res->errors = $st->errorInfo();
				return $res;
			}
			
			$base_objs = [ ];
			$increments_from_stalled_joins = [ ];
			
			$destall = function(&$join) use (&$increments_from_stalled_joins, &$destall) {
				foreach($join->joins as &$_j)
					$destall($_j);
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
			
			$row = $st->fetch(PDO::FETCH_ASSOC);
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
					$row = $st->fetch(PDO::FETCH_ASSOC);
				unset($obj);
			}
			
			$res = new TMResult(TMResult::Success);
			$res->result = &$base_objs;
			return $res;
		}
		
		
		// Update: update a value in the table
		//  - returns:
		//    - an array of errors if the updated values fail validation
		//    - false on db error
		//    - the number of altered rows on success
		
		static function update($updates, $conditions, $debug = false) {
			self::$bind_params = [ ];
			
			if ((is_array($conditions) && count($conditions) == 0) ||
				(!is_array($conditions) && !($conditions instanceof Condition))) {
				$res = new TMResult(TMResult::InvalidConditions);
				return $res;
			}
			
			$t_name = &self::getTableName();
			
			// Validate updates
			$errors = self::validateArray($updates);    // [col => val, ...]
			if (count($errors)) {
				$res = new TMResult(TMResult::InvalidData);
				$res->errors = $errors;
				return $res;
			}
			
			// Validate conditions
			$errors = self::validateConditions($conditions);
			if (count($errors)) {
				$res = new TMResult(TMResult::InvalidConditions);
				$res->errors = $errors;
				return $res;
			}
			
			$set_subqs = [ ];
			foreach ($updates as $c => $v) {
				$set_subqs []= "$c = ?";
				self::$bind_params []= $v;
			}
			$set_subqs = 'set ' . implode(', ', $set_subqs);
			
			$cond = self::getCondStr_AndAddBindParams($conditions);
			
			$q = "update $t_name $set_subqs $cond";
			if ($debug) var_dump($q);
			$st = self::$pdo->prepare($q);
			
			self::bindBindParams($st);
			
			$r = $st->execute();
			if ($r) {
				$res = new TMResult(TMResult::Success);
				$res->result = $st->rowCount();
				return $res;
			}
			else {
				$res = new TMResult(TMResult::InternalError);
				$res->errors = $st->errorInfo();
				return $res;
			}
		}
		
		
		// Insert: insert the object into the table
		//  - returns:
		//    - an array of errors if illegal values encountered
		//    - false on db error
		//    - the insert id on success
		
		function insert($debug = false) {
			self::$bind_params = [ ];
			$t_name = &self::getTableName();
			$fields = get_object_vars($this);
			
			// Check fields are legal
			$errors = self::validateArray($fields, true);
			if (count($errors)) {
				$res = new TMResult(TMResult::InvalidData);
				$res->errors = $errors;
				return $res;
			}
			
			$cols = $vals = [ ];
			
			foreach ($fields as $c => $v) {
				if ($v === null) continue;
				$cols []= $c;
				$vals []= '?';
				self::$bind_params []= $v;
			}
			
			$cols = implode(', ', $cols);
			$vals = implode(', ', $vals);
			
			$q = "insert into $t_name ( $cols ) values ( $vals )";
			if ($debug) var_dump($q);
			$st = self::$pdo->prepare($q);
			
			self::bindBindParams($st);
			
			$r = $st->execute();
			if ($r) {
				$res = new TMResult(TMResult::Success);
				$res->result = (int) self::$pdo->lastInsertId();
				return $res;
			}
			else {
				$res = new TMResult(TMResult::InternalError);
				$res->errors = $st->errorInfo();
				return $res;
			}
		}
		
	}

?>
