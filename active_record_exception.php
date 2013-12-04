<?php
class ActiveRecordException extends Exception
{
	const UNKNOWN_PROPERTY 		= 1;
	const UNKNOWN_METHOD 		= 2;
	const DEPRECIATED_METHOD 	= 3;
	const MISSING_PARAMETERS 	= 4;
	const INVALID_SQL 			= 5;
	const MEMORY_LIMIT_HIT 		= 6;
	const NO_PRIMARY_KEY_FOUND 	= 7;
	const DEPRECIATED_PROPERTY 	= 8;
	const MISSING_DATABASE_NAME = 9;
	const DATABASE_INSTANCE_MISSING = 10;
	
	/**
	 * Returns a tidy human readable exception code translation, e.g 1 translates to 'Unknown Property'
	 */
	public function humanize_exception_code($code)
	{
		$const_map = array(
			UNKNOWN_PROPERTY 		=> 'Unknown Property',
			UNKNOWN_METHOD 			=> 'Unknown Method',
			DEPRECIATED_METHOD		=> 'Method has been depreciated',
			MISSING_PARAMETERS 		=> 'You are missing some parameters in your function call',
			INVALID_SQL 			=> 'The SQL is invalid',
			MEMORY_LIMIT_HIT 		=> 'PHP memory limit exceeded in last operation',
			NO_PRIMARY_KEY_FOUND	=> 'Table has no primary key defined',
			DEPRECIATED_PROPERTY 	=> 'This property has been depreciated and should not be used',
			MISSING_DATABASE_NAME 	=> 'No database name given in constructor method',
			DATABASE_INSTANCE_MISSING => 'Database instance does not exist');
			
		return isset($const_map[$code]) ? $const_map[$code] : 'Unknown Exception';
	}
}
?>