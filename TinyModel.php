<?

	/*
	 *
	 * TinyModel - a sort of model type thing
	 *
	 * Such that objects representing database tables can be easily defined by subclassing.
	 * Functionality is exposed through the methods `fetch`, `insert`, and `update`,
	 * and nice, nested objects are returned.
	 *
	 * Copyright (c) 2010 - Ben Hallstein - ben.am
	 * Published under the MIT license - http://opensource.org/licenses/MIT
	 *
	 */
	
	// Some constant definitions
	define('MOD_RECENT_CONDITION', '***87hdsf2890***');			// last 2 weeks
	define('MOD_RECENT_PERIOD', 1209600);
	define('MOD_VERY_RECENT_CONDITION', '***1lg976syb0***');	// last 3.5 hours
	define('MOD_VERY_RECENT_PERIOD', 12600);
	
	class Join {
		public function __construct($cl, $co, $j = array()) {
			$this->class = $cl;
			$this->cols  = is_array($co) ? $co : array($co);
			$this->joins = is_array($j) ? $j : array($j);
		}
	}
	
	class TinyModel {
		
		static function tableCols() {
			$rc = new ReflectionClass(get_called_class());
			return $rc->getConstants();
		}
		static function tableName() {
			$class = strtolower(get_called_class());
			$c = substr($class, -1);
			$c2 = substr($class, -2, -1);
			if ($c == 'y' && !preg_match('/[aeiou]+/', $c2))
				return substr($class, 0, -1) . 'ies';
			if ($c == 'h')
				return $class . 'es';
			return $class . 's';
		}
		
		static function objFromRow($row, $prefix) {
			$cols = array_keys(self::tableCols());
			$new_obj = new static();
			$n_imported_columns = 0;
			foreach($cols as $col)
				if (null !== ($new_obj->$col = $row[$prefix . '_' . $col]))
					$n_imported_columns++;
			return ($n_imported_columns == 0) ? null : $new_obj;
		}
		
		static function formatConditions($conditions, $prefix = '', $use_or_conditions = false) {
			if (count($conditions) == 0) return '';

			if ($prefix !== '') $prefix .= '.';

			$subq = array();
			foreach ($conditions as $col => $val) {
				if ($val === MOD_RECENT_CONDITION)
					$s = "unix_timestamp(now()) - unix_timestamp($col) < " . MOD_RECENT_PERIOD;
				else if ($val === MOD_VERY_RECENT_CONDITION)
					$s = "unix_timestamp(now()) - unix_timestamp($col) < " . MOD_VERY_RECENT_PERIOD;
				else if ($col === 'password')
					$s = "{$prefix}password = md5(sha(concat(salt, '" . mysql_real_escape_string($val) . "')))";
				else
					$s = $prefix . mysql_real_escape_string($col) . " = " . (
							(is_int($val) or is_float($val)) ? $val : ("'" . mysql_real_escape_string($val) . "'")
						);
				$subq []= $s;
			}
			
			return 'where ' . implode($use_or_conditions ? ' or ' : ' and ', $subq);
		}
		
		static function validate($fields) {
			$consts = self::tableCols();
			$errors = false;
			
			foreach($fields as $col => $val) {
				if (!isset($consts[$col]))
					$errors[$col] = 'Illegal table field';
				else if ($val !== null) {
					$t = $consts[$col];
					$r = false;

					if ($t == 'float')             $r = is_float($val);
					else if ($t == 'int')          $r = is_int($val);
					else if ($t == 'timestamp')    $r = mb_ereg('^\d{2,4}.\d\d.\d\d \d\d.\d\d.\d\d$', $val);
					else if ($t == 'alphanumeric') $r = mb_ereg_match('^[a-zA-Z0-9]+$', $val);
					else if ($t == 'text') 		   $r = true;	// We should probably do something here!
					else if ($t == 'url')
						$r = filter_var(mb_ereg_replace('[^\x00-\x7f]', '-', $val), FILTER_VALIDATE_URL);
					else if ($t == 'email')
						$r = filter_var(mb_ereg_replace('[^\x00-\x7f]', '-', $val), FILTER_VALIDATE_EMAIL);
						
					if (!$r) {
						$errors[$col] = 'Illegal value';
						pxLog("Model_class: illegal value in validate(): $col => $val");
					}
				}
			}
			
			return $errors;			
		}
		
		public function wouldDifferFromRow(&$row, $prefix) {
			$id_column = array_shift(array_keys(self::tableCols()));
			return $row["{$prefix}_$id_column"] != $this->$id_column;
		}
		
		public function appendToArray($arrname, $val) {
			if (!isset($this->$arrname) or !is_array($this->$arrname))
				$this->$arrname = array();
			array_push($this->$arrname, $val);
		}

		static function fetch($conditions = array(), $joins = array(), $use_or_conditions = false, $debug = false) {
			$table = self::tableName();
			$cols  = self::tableCols();
			
			foreach($cols as $colname => $coltype) $q []= "a.$colname as a_{$colname}";
			$query_select_columns = array( implode(', ', $q) );
			
			$query_from_tables = array();
			$query_joins       = array();
			$i = 0;
			
			$add_join = function(&$join, &$parent = null) use (&$i, &$query_select_columns, &$query_joins, &$add_join) {
				$join->prefix = $prefix = chr($i++ + 98);
				$join->parent = $parent;
				$join->stalled = false;
				$parent_prefix = $parent ? $parent->prefix : 'a';
				
				$cla = $join->class;
				$table = $cla::tableName();
				$cols  = $cla::tableCols();
				
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
			
			$cond = self::formatConditions($conditions, 'a', $use_or_conditions);
            
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
				$table = $cla::tableName();
				$prefix = $j->prefix;
				
				$id_column = array_shift(array_keys($cla::tableCols()));
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
		
		static function update($updates, $conditions) {
			$t_name = self::tableName();
			
			if ($errors = self::validate($updates))		// [col => val, ...]
				return $errors;
			
			foreach($updates as $c => $v)
				$set []= "$c = " . ((is_int($v) or is_float($v)) ? $v : "'" . mysql_real_escape_string($v) . "'");
			$set = 'set ' . implode(', ', $set);
			
			$cond = self::formatConditions($conditions);
			
			$q = "update $t_name $set $cond";
			$r = mysql_query($q);
			return ($r ? mysql_affected_rows() : false);
		}
		
		function insert() {
			$t_name = self::tableName();
			
			$fields = get_object_vars($this);
			
			if ($errors = self::validate($fields))
				return $errors;
			
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
