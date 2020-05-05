

- Tests for TinyModel -


How to run:

	Ensure you have cd'd into the Tests directory, then you can run:

	./cmd_createTestDB      # create the TinyModel_Test database and user
	                        # (will prompt for mysql root password)

	./cmd_runTests          # run the tests
	


Some info:

	DB-related testing is a bit of a pain, because of all the data you need to throw around.
	
	This test file use PHPUnit, which has some built-in features for databases. The state of
	the DB is automatically reset for each test function to the contents of initial_test_data.xml:
	
		protected function getDataSet() {
			return $this->createMySQLXMLDataSet('initial_test_data.xml');
		}
	
	After the (slightly cursory) update/insert tests, we then check table state against 2 more
	XML files (which are near copies of the first):
	
		$queryTable = $this->getConnection()->createQueryTable('things', 'select * from things');
		$expectedTable = $this->createMySQLXMLDataSet('test_data_after_insert.xml')->getTable('things');
		$this->wrapAssert(assertTablesEqual, [$expectedTable, $queryTable], 'Insert: expected effect when successful');
	
	To test TinyModel effectively, a set of data to bombard the various type- and restriction-checking with
	is required. This is specified in validation_test_data.php, e.g.:
	
		[
			'field' => 'password', value => 'I am thirty two characters long.',
			'res_exp' => $resexp_success(),
			'class' => User,
			'description' => 'col maxlength restriction (char - characters success@32-ascii)'
		],
		...
	
	To test condition and update value validation, we can then loop over the data set.
	
	For insert validation, we instead combine various failing test data into just a few individual
	insert() calls, and check in the TMResult object that all of the proper errors have been detected.
	
	To better sectionalise everything, I've used PHPInfo's usual test functions to wrap multiple tests.
	The progress of individual tests is then indicated by using the wrapAsser() method, which prints a
	description underneath PHPInfo's not very descriptive '.'. This gives more, better info on what
	tests are being run, and splits testing up into sections, which I find preferable to having a zillion
	little functions.
	
