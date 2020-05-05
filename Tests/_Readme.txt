

- Tests for TinyModel -


How to run:

	(These tests require PHPUnit to be installed.)
	Ensure you have cd'd into the Tests directory, then you can run:

	./cmd_createTestDB      # create the TinyModel_Test database and user
	                        # (will prompt for mysql root password)

	./cmd_runTests          # run the tests



Some info:

	DB-related testing is a bit of a pain, because of all the data you need to throw around.
	
	These tests use PHPUnit, which has some built-in features for databases. The state of the
	DB is auto-reset before the invocation of each test function, using the contents of the
	initial_test_data.xml file:
	
	<?
		protected function getDataSet() {
			return $this->createMySQLXMLDataSet('initial_test_data.xml');
		}
	?>
	
	So to check update/insert has the desired effect, we can then check table state against two
	more XML files (which are near copies of the first), e.g.:
	
	<?
		$queryTable = $this->getConnection()->createQueryTable('things', 'select * from things');
		$expectedTable = $this->createMySQLXMLDataSet('test_data_after_insert.xml')->getTable('things');
		$this->wrapAssert(assertTablesEqual, [$expectedTable, $queryTable], 'Insert: expected effect when successful');
	?>
	
	To test TinyModel effectively, a set of data is required which which to bombard its various type-
	and restriction-checking features. This is specified in validation_test_data.php, e.g.:
	
	<?
		[
			'field' => 'password', value => 'I am thirty two characters long.',
			'res_exp' => $resexp_success(),
			'class' => User,
			'description' => 'col maxlength restriction (char - characters success@32-ascii)'
		],
		...
	?>
	
	To test condition and update value validation, we then loop over the data set, as in the
	test_ConditionValidation() and test_UpdateValidation() methods.
	
	For insert validation, we instead combine various bits of failing insert data into just a
	few individual insert() calls, and check in the TMResult object that all of the proper errors
	have been detected.
	
	To better sectionalise everything, I've used PHPUnit's usually individual test functions to
	wrap multiple tests. The progress of individual tests is then indicated by using the wrapAssert()
	method, which prints a description of the actual test(s) being performed underneath PHPInfo's usual
	not very descriptive output of '.'.
	
	This gives more, better output regarding the tests being run, and splits testing up into sections,
	which I find preferable to writing a zillion little functions in this case.

	Ben Hallstein, Nov 2014
	:)
