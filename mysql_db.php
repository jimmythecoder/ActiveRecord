<?php
define('DB_ADAPTER','mysql');
define('DB_DEFAULT_PORT','3306');
define('DB_DATE_FORMAT','Y-m-d');
define('DB_DATETIME_FORMAT','Y-m-d H:i:s');

/**
 * MySQL DB driver for ActiveRecord
 */
class mysql_db extends DB
{
	/**
	 * Return an associative array of table columns with their meta data 
	 * 	column => (name,is_not_null,size,type,default,is_primary_key)
	 * @param string $table_name name of the table
	 * @return array columns and meta info
	 */
	public function get_columns_and_meta_for_table($table_name)
	{
		$sql = "SHOW COLUMNS FROM $table_name";

		if(!$arr_columns = $this->get_records_as_array($sql))
			throw new Exception('No records returned for show column query!');
	
		$arr_column_meta = array();

		foreach($arr_columns as $key => $column)
		{
			preg_match('/^([a-z]+)/',$column['type'],$type);
			preg_match('/\(([0-9]+)\)/',$column['type'],$size);
			
			$arr_column_meta[$column['field']] = array(
				'name' 			=> $column['field'],
				'is_not_null' 	=> $column['null'] == 'NO' || empty($column['null']),
				'size' 			=> isset($size[1]) ? $size[1] : null,
				'type' 			=> $type[1],
				'default' 		=> $column['default'],
				'is_primary_key'=> $column['key'] == 'PRI');
		}

		return $arr_column_meta;
	}
	
	/**
	 * Returns an array of primary key column names for the given table
	 * @param string $table_name name of the table in the database
	 */
	public function get_primary_keys($table_name)
	{
		$sql = "SHOW COLUMNS FROM $table_name";
		
		if(!$arr_columns = $this->get_records_as_array($sql))
			throw new Exception('No records returned for show column query!');
	
		$arr_primary_keys = array();
		
		foreach($arr_columns as $key => $column)
		{
			if($column['key'] == 'PRI')
				$arr_primary_keys[] = $column['field'];
		}
		
		return $arr_primary_keys;
	}
	
	/**
	 * Returns an array of foreign keys on a table
	 * @param string $table_name table name
	 * @return array table => foreign table column, foreign key column name
	 */
	public function get_foreign_keys($table_name)
	{
	    $table_schema 	= $this->get_first_row("SHOW CREATE TABLE $table_name");
		$create_sql 	= $table_schema['create table'];

	    $matches = array();

	    if(!preg_match_all('/FOREIGN KEY \("(.*?)"\) REFERENCES "(.*?)" \("(.*?)"\)/', $create_sql, $matches)) 
	    	return false;
	    	
	 	$foreign_keys 	= array();	 	 
	    $num_keys 		= count($matches[0]);
	    for($i = 0; $i < $num_keys; $i++) 
	    {
	        $my_field  = explode('`, `', $matches[1][$i]);
	        $ref_table = $matches[2][$i];
	        $ref_field = explode('`, `', $matches[3][$i]);
	
	        $foreign_keys[$ref_table] = array();
	        $num_fields               = count($my_field);
	        for( $j = 0;  $j < $num_fields;  $j ++ ) 
	        	$foreign_keys[$ref_table][$ref_field[$j]] = $my_field[$j];
	    }		

		return $foreign_keys;
	}	
	
	/**
	 * Returns a unified standard type name for the given database
	 * @param string $data_type_name database type name e.g. varchar
	 * @return string one of(character,text,binary,date,datetime,numeric)
	 */
	public function unify_data_type($data_type_name)
	{
		$lower_cased_data_type_name = strtolower($data_type_name);
		
		$arr_type_associations = array(
			'string'	=> 'character',
			'char' 		=> 'character',
			'varchar' 	=> 'character',
			'tinyblob' 	=> 'character',
			'tinytext' 	=> 'character',
			'enum' 		=> 'character',
			'set' 		=> 'character',
			'text' 		=> 'text',
			'longtext' 	=> 'text',
			'mediumtext'=> 'text',
			'image' 	=> 'binary',
			'blog' 		=> 'binary',
			'longblob'	=> 'binary',
			'mediumblob'=> 'binary',
			'year' 		=> 'date',
			'date' 		=> 'date',
			'time' 		=> 'datetime',
			'datetime' 	=> 'datetime',
			'timestamp'	=> 'datetime',
			'int' 		=> 'numeric',
			'integer' 	=> 'numeric',
			'bigint' 	=> 'numeric',
			'mediumint' => 'numeric',
			'smallint' 	=> 'numeric',
			'float' 	=> 'numeric',
			'double' 	=> 'numeric',
			'decimal' 	=> 'numeric',
			'dec' 		=> 'numeric',
			'fixed' 	=> 'numeric'
		);
		
		if(isset($arr_type_associations[$lower_cased_data_type_name]))
			return $arr_type_associations[$lower_cased_data_type_name];
		else
			return 'unknown';
	}
}
?>