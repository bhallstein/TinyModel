<?php
 /*
  * Diff/diff.php
  *
  * Database diffing
  *
  */

	require_once(__DIR__ . '/helpers.php');

	if (!TinyModel::$pdo) {
		Helpers::p(clr('Error:', 'red', true),  " TinyModel::$pdo is null\n");
		return;
	}

	class TMDiff {
		public $tables_for_removal;
		public $classes_for_addition;
		public $table_alterations;

		public function __construct() {
			$this->tables_for_removal = [ ];
			$this->classes_for_addition = [ ];
			$this->table_alterations = [ ];
		}
	}

	function ech() {
		if (!Helpers::get_option('--silent')) {
			foreach (func_get_args() as $a)
				echo $a;
		}
	}
	function p($x) {
		Helpers::p(json_encode($x, JSON_PRETTY_PRINT), "\n\n");
	}

	$diff = new TMDiff;


	// 1. Get TM model info
	$model_classes = Helpers::get_subclasses(TinyModel);
	$model_table_names = array_map(function($c) { return $c::getTableName(); }, $model_classes);


	// 2. Get DB tables
	$db_tables = Helpers::get_table_descriptions_for_DB(TinyModel::$pdo);
	$db_table_names = array_keys($db_tables);


	// 3. Check for table-level alterations
	$diff->tables_for_removal = array_diff($db_table_names, $model_table_names);
	$diff->classes_for_addition = array_map(
		function($tbl) use (&$model_classes, &$model_table_names) {
			return $model_classes[array_search($tbl, $model_table_names)];
		},
		array_values(array_diff($model_table_names, $db_table_names))
	);


	// 4. Check for column-level alterations
	foreach ($model_classes as $class) {
		$t = $class::getTableName();
		if (!in_array($t, $db_table_names)) continue;

		$model_cols = $class::describe();
		$db_cols    = $db_tables[$t];

		// Columns to add & remove
		$tbl_alterations = [ ];
		$tbl_alterations['remove_cols'] = array_keys(array_diff_key($db_cols, $model_cols));
		$tbl_alterations['add_cols'] = array_keys(array_diff_key($model_cols, $db_cols));

		// Columns to alter
		$tbl_alterations['modify_cols'] = [ ];
		$colmods = &$tbl_alterations['modify_cols'];
		$type_mapping = [
			'id'         =>  [ 'bigint', 'int' ],
			'int'        =>  [ 'bigint', 'int', 'mediumint', 'smallint', 'tinyint' ],
			'float'      =>  [ 'float', 'double' ],
			'timestamp'  =>  [ 'timestamp' ],
			'char'       =>  [ 'char', 'varchar' ],  // NB: internally to TM type is varchar,
			'varchar'    =>  [ 'char', 'varchar' ],  //     even if the user set it to char
			'text'       =>  [ 'longtext', 'mediumtext', 'text', 'tinytext' ],
		];
		foreach ($model_cols as $col_name => $col_def) {
			if (!isset($db_cols[$col_name]))
				continue;

			// Check types match
			$col = new Column($col_def);
			$db_type_for_col = $db_cols[$col_name]['type'];

			if (!in_array($db_type_for_col, $type_mapping[$col->type])) {
				$colmods[$col_name] = [ ];
				$colmods[$col_name]['type'] = [
					'from' => $db_type_for_col,
					'to'   => $col->type
				];
			}

			// check 'not null'
			$differing_attributes = [ ];
			if ($col->rNotNull != $db_cols[$col_name]['notnull']) {
				$differing_attributes['notnull'] = [
					'from' => $db_cols[$col_name]['notnull'],
					'to'   => $col->rNotNull
				];
			}

			// int columns: check unsigned
			if (
				$col->type == 'int' &&
				$col->rPositiveNumber != $db_cols[$col_name]['unsigned']
			)
			{
				$differing_attributes['unsigned'] = [
					'from' => $db_cols[$col_name]['unsigned'],
					'to'   => $col->rPositiveNumber
				];
			}

			// char columns: check length
			if (
				$col->type == 'varchar' &&
				$col->maxLength &&
				$col->maxLength !== $db_cols[$col_name]['length']
			)
			{
				$differing_attributes['character length'] = [
					'from' => $db_cols[$col_name]['length'],
					'to'   => $col->maxLength
				];
			}

			if (count($differing_attributes) > 0) {
				if (!isset($colmods[$col_name])) $colmods[$col_name] = [ ];
				$colmods[$col_name] = [
					'attributes' => $differing_attributes
				];
			}
		}
		if (!(
			empty($tbl_alterations['remove_cols']) &&
			empty($tbl_alterations['add_cols']) &&
			empty($tbl_alterations['modify_cols'])
		))
		{
			$diff->table_alterations[$class] = $tbl_alterations;
		}
	}


	// 5. Output proposed changes to user and prompt for confirmation
	function print_diff_and_get_warnings($diff) {
		$warnings = [
			'tables_removed' => count($diff->tables_for_removal),
			'columns_removed' => 0,
			'columns_modified' => 0,
		];

		$dbname = Helpers::get_db_name(TinyModel::$pdo);
		Helpers::p(Helpers::clr("Operations on DB '$dbname':", 'normal', true));
		Helpers::p('--------------------------------------------------------');
		if (count($diff->tables_for_removal) > 0) {
			Helpers::p("Tables to be removed: ");
			foreach ($diff->tables_for_removal as $t)
				Helpers::p('  - ', Helpers::clr($t, 'red', true));
		}
		if (count($diff->classes_for_addition) > 0) {
			$tblnames = array_map(
				function($cla) { return $cla::getTableName(); },
				$diff->classes_for_addition
			);
			Helpers::p('Tables to be created:');
			foreach ($tblnames as $t) Helpers::p('  - ', Helpers::clr($t, 'green', true));
		}
		if (is_array($diff->table_alterations) && count($diff->table_alterations) > 0) {
			Helpers::p('Tables requiring alterations:');

			foreach ($diff->table_alterations as $cla => $details) {
				$t = $cla::getTableName();
				Helpers::p('  - ', Helpers::clr($t, 'normal', true), ':');

				if (isset($details['remove_cols']) && count($details['remove_cols']) > 0) {
					$warnings['columns_removed'] += count($details['remove_cols']);
					Helpers::p("      columns to remove: ", Helpers::implcol($details['remove_cols'], 'red', true));
				}
				if (isset($details['add_cols']) && count($details['add_cols']) > 0)
					Helpers::p("      columns to create: ", Helpers::implcol($details['add_cols'], 'green', true));

				if (isset($details['modify_cols']) && count($details['modify_cols']) > 0) {
					$warnings['columns_modified'] += count($details['modify_cols']);
					Helpers::p("      columns to modify:");
					$pad_length = max(array_map(function($x) { return mb_strlen($x); }, array_keys($details['modify_cols'])));
					foreach ($details['modify_cols'] as $colname => $mdfcn) {
						Helpers::p("         ", str_repeat(' ', $pad_length - mb_strlen($colname)),
								     Helpers::clr($colname, 'yellow', true), "  ",
									  Helpers::get_modification_expln($mdfcn));
					}
				}
			}
			Helpers::p('--------------------------------------------------------');
		}

		return $warnings;
	}

	$modifs_present = (
		count($diff->tables_for_removal) > 0 ||
		count($diff->classes_for_addition) > 0 ||
		count($diff->table_alterations) > 0
	);
	if (!$modifs_present) {
		$dbname = Helpers::get_db_name(Tinymodel::$pdo);
		Helpers::p(Helpers::clr('Nothing to do!', 'green', true), " Model and '$dbname' are in sync.");
		return;
	}

	// ech("Diff:\n");
	// p($diff);

	$warnings = print_diff_and_get_warnings($diff);
	if ($dangerous_modifs_present = (max($warnings) > 0)) {
		ech(Helpers::clr("\nWARNING:\n", 'red', true));
		ech("Proceeding from here will");
		$r_tbl = $warnings['tables_removed'] > 0;
		$r_col = $warnings['columns_removed'] > 0;
		$m_col = $warnings['columns_modified'] > 0;
		if ($r_tbl || $r_col) {
			ech(" destroy");
			if ($r_tbl) {
				ech(" {$warnings['tables_removed']} tables");
				if ($r_col) ech(', and');
			}
			if ($r_col) {
				ech(" {$warnings['columns_removed']}");
				ech(' columns');
				if ($r_tbl) ech(' in other tables');
			}
			if ($m_col) ech(", and");
		}
		if ($m_col) {
			ech(" modify {$warnings['columns_modified']}");
			if ($r_col) ech(' further');
			ech(' columns, perhaps destructively');
		}
		ech(".\n");

		$should_prompt = !(
			Helpers::get_option('--do-not-prompt-when-destroying-data') ||
			Helpers::get_option('--silent')
		);
		if ($should_prompt) {
			ech("\nAre you sure you wish to continue? ");
			$response = readline();
			if (substr($response, 0, 1) != 'y') {
				ech("OK, bye.\n\n");
				exit;
			}
			ech(Helpers::clr('Really sure? ', 'normal', true));
			$response = readline();
			if (substr($response, 0, 1) != 'y') {
				ech("OK, bye.\n\n");
				exit;
			}
			ech("OK, applying changes... ");
		}
	}


	// 6. Make the proposed changes

	function apply_diff($diff, $pdo) {
		$statements = [ ];
		$errors = [ ];

		// 1. Remove tables - !!
		foreach ($diff->tables_for_removal as $t) {
			$statements []= "drop table `$t`";
		}

		// 2. Create new tables
		foreach ($diff->classes_for_addition as $cla) {
			$t = $cla::getTableName();
			$q_cols = [ ];
			$cla_descr = $cla::describe();
			foreach ($cla_descr as $colname => $str) {
				$col = new Column($str);
				$q_cols []= "" . Helpers::get_SQL_description($col, $colname);
			}
			$statements []= "create table `$t` ( " . implode(', ', $q_cols) . ' ) CHARSET=utf8 COLLATE utf8_unicode_ci';
		}

		// 3. Column modifications
		foreach ($diff->table_alterations as $cla => $details) {
			$t = $cla::getTableName();
			$cla_descr = $cla::describe();

			// A. Remove columns
			foreach ($details['remove_cols'] as $col) {
				$statements []= "alter table `$t` drop `$col`";
			}

			// B. Add new columns
			foreach ($details['add_cols'] as $colname) {
				$col = new Column($cla_descr[$colname]);
				$statements []= "alter table `$t` add column " . Helpers::get_SQL_description($col, $colname, true);
			}

			// C. Modify columns
			foreach ($details['modify_cols'] as $colname => $mdfcns) {
				$col = new Column($cla_descr[$colname]);
				$statements []= "alter table `$t` modify " . Helpers::get_SQL_description($col, $colname);
			}
		}

		foreach ($statements as $q) {
			$st = $pdo->prepare($q);
			$r = $st->execute();
			if (!$r) {
				$errors []= $q;
				$should_prompt = !(
					Helpers::get_option('--do-not-prompt-when-destroying-data') ||
					Helpers::get_option('--silent')
				);
				if ($should_prompt) {
					ech(Helpers::clr("Error: ", 'red', true), "Couldn't execute the following SQL statement:\n");
					ech('  ', $q, "\n");
					ech("\nKeep going? "); $response = readline();
					if (substr($response, 0, 1) != 'y') {
						ech("OK, bye.\n\n");
						exit;
					}
				}
			}
		}

		return $errors;
	}
	ech("\n");
	$sql_errors = apply_diff($diff, Tinymodel::$pdo);

	if (count($sql_errors) == 0) {
		ech(Helpers::clr('Success!', 'green', true), "\n",
		    'All changes were applied. Model and DB are now in sync.', "\n");
	}
	else {
		echo Helpers::clr('Error!', 'red', true), "\n",
		    'The following statements could not be executed:', "\n";
		foreach ($sql_errors as $e)
			echo "- '$e'\n";
	}


	// Options: --do-not-prompt-when-destroying-data
	//          --silent     (implies --do-not-prompt)
	//                       If SQL errors are encountered, an error message will be printed,
	//                       EVEN IF --silent is used.
