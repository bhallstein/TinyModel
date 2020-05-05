<?php

class Helpers {

	public static function type_from_mysql_type_string($s) {
		$pos_first_bracket = mb_strpos($s, '(');
		$pos_first_space   = mb_strpos($s, ' ');
		if ($pos_first_bracket === false) $pos_first_bracket = mb_strlen($s);
		if ($pos_first_space   === false) $pos_first_space   = mb_strlen($s);

		return mb_substr($s, 0, min($pos_first_bracket, $pos_first_space));
	}

	public static function char_length_from_mysql_type_string($s) {
		$matches = [ ];
		preg_match('/\((\d+)\)/', $s, $matches);
		return (int) $matches[1];
	}

	public static function clr($str, $col, $bold = false) {
		static $colcodes = [
			'red'    => ';31',    'green'  => ';32',
			'yellow' => ';33',    'blue'   => ';34',
			'normal' => ''
		];
		return "\033[" . ($bold ? '1' : '0') . $colcodes[$col] . 'm' . $str . "\033[0m";
	}

	public static function implcol($arr, $col, $bold, $sep = ', ') {
		return implode(
			$sep,
			array_map(
				function($s) use ($col, $bold) { return self::clr($s, $col, $bold); },
				$arr
			)
		);
	}

	public static function get_modification_expln($m) {
		$out = [ ];
		if (isset($m['type'])) {
			$out []= "type({$m['type']['from']} -> {$m['type']['to']})";
		}
		if (isset($m['attributes'])) {
			$atr = [ ];
			foreach ($m['attributes'] as $a => $info) {
				if ($a == 'notnull') {
					$atr []= ($info['to'] ? 'add' : 'remove') . " 'notnull'";
				}
				else if ($a == 'unsigned') {
					$atr []= ($info['to'] ? 'add' : 'remove') . " 'unsigned'";
				}
				else if ($a == 'character length') {
					$atr []= "length({$info['from']} -> {$info['to']})";
				}
			}
			$out []= implode(', ', $atr);
		}
		return implode(' ', $out);
	}


	// Get tables in DB
	public static function get_table_descriptions_for_DB($pdo) {
		$st = $pdo->prepare('select database()');
		$r = $st->execute();
		if (!$r) {
			echo "ERROR: couldn't get database name\n";
			return;
		}
		$results = $st->fetchAll();
		if (count($results) != 1) {
			echo "ERROR: couldn't get database name\n";
			return;
		}
		$dbname = $results[0][0];

		// Get list of table names
		$st = $pdo->prepare('select table_name from information_schema.tables where table_schema=:db');
		$st->bindParam(':db', $dbname);
		$r = $st->execute();
		if (!$r) {
			echo "ERROR: ";
			echo $st->errorInfo();
			return;
		}

		$results = $st->fetchAll();
		$tableNames = [ ];
		foreach ($results as $tbl)
			$tableNames []= $tbl['table_name'];

		$descr = [ ];

		// Get column descriptions
		foreach ($tableNames as $tbl) {
			$st = $pdo->prepare("select * from $tbl limit 1");
			$r = $st->execute();
			if (!$r) {
				echo "ERROR 2: ";
				var_dump($st->errorInfo());
				return;
			}

			$descr[$tbl] = [ ];

			// Get column info
			$st = $pdo->prepare("desc $tbl");
			$r = $st->execute();
			if (!$r) {
				echo "ERROR 2: ";
				var_dump($st->errorInfo());
				return;
			}
			while ($dbcol = $st->fetch(PDO::FETCH_OBJ)) {
				$col_descr = [
					'type' => Helpers::type_from_mysql_type_string($dbcol->Type),
					'notnull' => ($dbcol->Null == 'NO'),
				];
				if ($col_descr['type'] == 'char' || $col_descr['type'] == 'varchar') {
					$col_descr['length'] = Helpers::char_length_from_mysql_type_string($dbcol->Type);
				}
				if (strpos($col_descr['type'], 'int') !== false) {
					$col_descr['unsigned'] = (strpos($dbcol->Type, 'unsigned') !== false);
				}

				$descr[$tbl][$dbcol->Field] = $col_descr;
			}
		}

		return $descr;
	}

	public static function get_subclasses($parent) {
		$res = [ ];
		foreach (get_declared_classes() as $cl) {
			if (is_subclass_of($cl, $parent))
				$res []= $cl;
		}
		return $res;
	}

	public static function get_SQL_description($tm_column, $name, $adding_column = false) {
		// type: int, float, char/varchar (these are equivalent), text, timestamp
		// restrictions: alphabetical, alphanumeric, email, url, positive, notnull, maxlength=N
		$s = "`$name`";

		if ($tm_column->type == 'id') {
			$s .= ' int unsigned auto_increment';
		}
		else if ($tm_column->type == 'int') {
			$s .= ' int';
			if ($tm_column->rPositiveNumber)  $s .= ' unsigned';
		}
		else if ($tm_column->type == 'float') {
			$s .= ' float';
		}
		else if ($tm_column->type == 'varchar') {
			$s .= ' varchar';
			if ($tm_column->maxLength)  $s .= "({$tm_column->maxLength})";
		}
		else if ($tm_column->type == 'text') {
			$s .= ' text';
		}
		else if ($tm_column->type == 'timestamp') {
			$s .= ' timestamp';
		}
		else {
			return null;
		}
		if ($tm_column->rNotNull)  $s .= ' not null';
		else                       $s .= ' null';

		if ($adding_column) {
			if ($tm_column->type == 'id')  $s .= ' primary key';
		}

		return $s;
	}
}
