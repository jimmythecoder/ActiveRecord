<?php
abstract class DB
{
	protected $dsn;
	
	protected $connection;
	
	protected $pdo_statement;
	
	/**
	 * Instance of self, so we can make this a singleton class
	 */
	protected static $instance;
	
	public function __construct($db_config)
	{
		$this->dsn = $db_config['adapter'] . ':host=' . $db_config['host'] . ';dbname=' . $db_config['database'] . ';port=' . $db_config['port'];
	
		$this->connect($db_config['username'], $db_config['password'], $db_config['pconnect']);
		
		self::$instance = $this;		
	}
	
	static function &get_instance()
	{
		return self::$instance;
	}
	
	/**
	 * Makes the actual connection the specified database using the internal dsn set
	 * @param string $user username to connect with
	 * @param string $pass password to connect with
	 */
	public function connect($user, $pass, $persistent = false)
	{
		try{
			$this->connection = new PDO($this->dsn, $user, $pass, $persistent ? array(PDO::ATTR_PERSISTENT => true) : null);
	
			$this->connection->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
			$this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, true); //Because MySQL native prepare is fucked
			
			//Setup UTF-8 Support and make mysql behave in standards compliance mode like postgresql
	  		$this->query('SET NAMES utf8');
	  		$this->query("SET SESSION sql_mode = 'POSTGRESQL'");
		}catch(PDOException $e){
			//This should be caught, logged and processed correctly in the application
			exit('Database connection failed using DSN ['.$this->dsn.']. Reason: ' . $e->getMessage());
		}
	}
	
	public function disconnect()
	{
		$this->connection = null;
	}
	
	public function is_connected()
	{
		return !is_null($this->connection);
	}
	
	public function get_last_insert_id($table, $column)
	{
		$sequence_name = $table .'_' . $column . '_seq';
		
		return $this->connection->lastInsertId($sequence_name);
	}
	
	public function query($sql, $params = array())
	{
		//if(config::get('core','enable_query_logging'))
		//	logger::log_message($sql,'query');

		if(empty($params))
			$this->pdo_statement = $this->connection->query($sql);
		else
		{
			$this->pdo_statement = $this->connection->prepare($sql);
			return $this->pdo_statement->execute($params);
		}
		
		return true;
	}

	/**
	 * Fetches all records returned by the given query into an associative array
	 * @param string $sql query to execute
	 * @param array $params singular array of params
	 * @return array associative array of records
	 */
	public function get_records_as_array($sql, $params = array())
	{
		if(!$this->query($sql, $params))
			throw new Exception('DB Query Failed! sql: ['.$sql.']');
		
		$arr_records = $this->pdo_statement->fetchAll(PDO::FETCH_ASSOC);
	
		$this->pdo_statement->closeCursor();
		
		return $arr_records;
	}
		
	public function get_first_row($sql, $params = array())
	{
		if(!$this->query($sql, $params))
			throw new Exception('DB Query Failed! sql: ['.$sql.']');
		
		$row = $this->pdo_statement->fetch(PDO::FETCH_ASSOC);
		
		$this->pdo_statement->closeCursor();
		
		return $row;
	}
	
	public function get_first_cell($sql, $params = array())
	{
		if(!$this->query($sql, $params))
			throw new Exception('DB Query Failed! sql: ['.$sql.']');
		
		$cell = $this->pdo_statement->fetchColumn(0);
		
		$this->pdo_statement->closeCursor();
		
		return $cell;
	}
	
	public function get_columns_and_meta_for_table($table_name)
	{
		throw new Exception('Method not implemented in driver [get_column_meta_for_table]');
	}
	
	public function get_primary_keys($table_name)
	{
		throw new Exception('Method not implemented in driver [get_primary_keys]');
	}
	
	public function get_foreign_keys($table_name)
	{
		throw new Exception('Method not implemented in driver [get_foreign_keys]');
	}
	
	public function escape_and_quote_string($unescaped_string)
	{
		if(get_magic_quotes_gpc())
			$unescaped_string = stripslashes($unescaped_string);
			
		$escaped_string = $this->connection->quote($unescaped_string);
		
		return $escaped_string;
	}
	
	public function begin_transaction()
	{
		$this->connection->beginTransaction();
	}
	
	public function commit_transaction()
	{
		$this->connection->commit();
	}
	
	public function rollback_transaction()
	{
		$this->connection->rollback();
	}
}
?>