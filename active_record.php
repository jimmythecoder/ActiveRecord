<?php
/**
 * A PHP5 implementation of ActiveRecord. Base class for all database table models
 * @usage extend class with a class the same name as the table
 *    class user extends ActiveRecord
 *    {
 		 function find_user_by_name($name)
 		 {
 		 	return $this->find_by_name($name);
 		 }
      }
 * {@link https://github.com/jimmythecoder/ActiveRecord Project Base}
 */
abstract class ActiveRecord
{
	/**
	 * Reference to a DB object
	 */
	protected $_db;
	
	/**
	 * Associative array of columns and their meta data
	 * [column_name] => [type,max_length,default_value,not_null,foreign_key,is_primary_key,value]
	 */
	protected $_columns;
	
	/**
	 * Column name of the primary key (May be multiple, so stores the first)
	 */
	protected $_primary_key;
	
	/**
	 * An array of errors thrown from this model, usually from validation failures
	 * @var array
	 */
	protected $_errors;
	
	/**
	 * A singular array of protected fields
	 * @var array
	 */	
	protected $_protected_fields;
	
	/**
	 * Associative array of foreign table objects loaded dynamically
	 */
	protected $_foreign_table_objects;
	
	
	public function __construct()
	{
		$this->_db 					= DB::get_instance();
		$this->_errors 				= array();
		$this->_protected_fields 	= array('id');
		$this->_foreign_table_objects 	= array();
		
		$this->load_column_data();
	}

	/**
	 * Sets the models protected fields (cannot be set from array or object methods)
	 * @param array $fields singular array of fields to protect
	 */
	public function set_protected_fields($fields)
	{
		$this->_protected_fields = array_merge($this->_protected_fields, $fields);
	}
		
	public function reset_record()
	{
		foreach($this->_columns as $column_name => $column_data)
			$this->_columns[$column_name]['value'] = null;
			
		$this->clear_all_errors();
	}
	
	public function load_column_data()
	{
		if(!$this->_columns = $this->_db->get_columns_and_meta_for_table($this->get_table_name()))
			return false;

		foreach($this->_columns as $column_name => $column)
		{
			if($column['is_primary_key'])
				$this->_primary_key = $column_name;
				
			$this->_columns[$column_name]['value'] 	= null;
		}
		
		if($foreign_keys = $this->_db->get_foreign_keys($this->get_table_name()))
		{
			foreach($foreign_keys as $foreign_table => $arr_keys_to_table)
			{
				foreach($arr_keys_to_table as $foreign_table_column => $foreign_key_column)
					$this->set_foreign_key($foreign_key_column, $foreign_table, $foreign_table_column);
			}
		}
	}
	
	/**
	 * Define a foreign key on a column
	 */
	public function set_foreign_key($column, $foreign_table, $foreign_table_column)
	{
		$this->_columns[$column]['foreign_key'] = array('table' => $foreign_table, 'column' => $foreign_table_column);
	}
	
	public function __set($property_name, $value)
	{
		$this->{"set_$property_name"}($value);
	}
	
	/**
	 * Overload foreign keys and property getters
	 * Foriegn keys allows us to use $this->customer->name where customer_id is an fk to customer table
	 * Getters allows us to call $this->property which will call the associated get method and use property from within $this->columns
	 */
	public function __get($property_name)
	{
		//Overload foriegn keys, so we can call $table1->table2->property etc
		$fk_column_name = $property_name . '_id';
		
		if($this->is_column_a_foreign_key($fk_column_name))
		{
			if(isset($this->_foreign_table_objects[$property_name]))
				return $this->_foreign_table_objects[$property_name];
			else
				return $this->load_foreign_key($fk_column_name);
		}
		else
			return $this->{"get_$property_name"}();
	}
	
	/**
	 * Overload the call method, so we can have rails like virtual functions. find_by_x_and_y($param1,$param2) ...
	 */
	public function __call($method, $arr_arguments)
	{
		//Overload the find_by_* and find_all_by_* methods
		if(preg_match('/^find_by_([a-z0-9_]+)/', $method, $matches))
			return $this->find_by_columns(array_combine(explode('_and_',$matches[1]), $arr_arguments));
		else if(preg_match('/^find_all_by_([a-z0-9_]+)/', $method, $matches))
			return $this->find_all_by_columns(array_combine(explode('_and_',$matches[1]), $arr_arguments));
		else if(preg_match('/^find_all_as_array_by_([a-z0-9_]+)/', $method, $matches))
			return $this->find_all_as_array_by_columns(array_combine(explode('_and_',$matches[1]), $arr_arguments));
		else if(preg_match('/^get_([a-z0-9_]+)/',$method, $matches))
		{	
			//Overload get_[property] methods
			if(isset($matches[1]))
			{
				$property_name = $matches[1];
				
				if(array_key_exists($property_name,$this->_columns))
					return $this->_columns[$property_name]['value'];
				else
					throw new ActiveRecordException('Property ' . $property_name . ' does not exist', ActiveRecordException::UNKNOWN_PROPERTY);
			}
			else
				throw new ActiveRecordException('Property not specified', ActiveRecordException::MISSING_PARAMETERS);
		}
		else if(preg_match('/^set_([a-z0-9_]+)/',$method, $matches))
		{	
			//Overload set_[property] methods
			if(isset($matches[1]))
			{
				$property_name = $matches[1];
				
				if(array_key_exists($property_name,$this->_columns))
					$this->_columns[$property_name]['value'] = $arr_arguments[0];
				else
					throw new ActiveRecordException('Property ' . $property_name . ' does not exist', ActiveRecordException::UNKNOWN_PROPERTY);
			}
			else
				throw new ActiveRecordException('Property not specified', ActiveRecordException::MISSING_PARAMETERS);
		}
		else	
			throw new ActiveRecordException('Call to unknown method ['.$method.'] backtrace: ' . print_r(debug_backtrace(),true), ActiveRecordException::UNKNOWN_METHOD);
	}
	
	public function find($id)
	{
		$sql = 'SELECT * FROM "' . $this->get_table_name() . '" WHERE ' . $this->_primary_key . ' = ?';
	
		if(!$record = $this->_db->get_first_row($sql, array($id)))
			return false;

		$this->set_properties_from_record($record);
		
		return true;
	}

	public function find_by_sql($sql, $params = array())
	{
		if(!$record = $this->_db->get_first_row($sql, $params))
			return false;

		$this->set_properties_from_record($record);
		
		return true;
	}
		
	/**
	 * Returns a numeric count for the number of records found
	 */
	 public function count($conditions = '', $params = array())
	 {
		$sql = 'SELECT COUNT(*) FROM "' . $this->get_table_name() . '" ' . $conditions;
	
		return (int)$this->_db->get_first_cell($sql, $params);
	 }
	
	/**
	 * Returns a reference to an array of active record objects
	 */
	public function &find_all($conditions = '', $params = array())
	{
		if(!$arr_records = $this->find_all_as_array($conditions, $params))
			return false;

		$arr_obj_records 	= array();
		$table_name			= $this->get_table_name();
		
		foreach($arr_records as $index => $record)
		{
			$arr_obj_records[$index] = ActiveRecord::load($table_name);
			$arr_obj_records[$index]->set_properties_from_record($record);
		}
		
		return $arr_obj_records;
	}
	
	public function find_all_as_array($conditions = '', $params = array())
	{
		$sql = 'SELECT "' . $this->get_table_name() . '".* FROM "' . $this->get_table_name() . '" ' . $conditions;
	
		return $this->_db->get_records_as_array($sql, $params);	
	}
	
	public function find_by_columns($arr_columns_and_values)
	{
		$sql = 'SELECT * FROM "' . $this->get_table_name() . '" WHERE ';
	
		$args = array();
		
		foreach($arr_columns_and_values as $column_name => $value)
			$args[] = $column_name . ' = ?';
	
		$sql .= implode(' AND ',$args);
	
		$sql .= ' LIMIT 1';

		if(!$record = $this->_db->get_first_row($sql, array_values($arr_columns_and_values)))
			return false;

		$this->set_properties_from_record($record);
		
		return true;		
	}

	public function find_all_by_columns($arr_columns_and_values, $append_sql = null)
	{
		if(!$arr_records = $this->find_all_as_array_by_columns($arr_columns_and_values, $append_sql = null))
			return false;
		
		$arr_obj_records 	= array();
		$table_name			= $this->get_table_name();
		
		foreach($arr_records as $index => $record)
		{
			$arr_obj_records[$index] = ActiveRecord::load($table_name);
			$arr_obj_records[$index]->set_properties_from_record($record);
		}
		
		return $arr_obj_records;		
	}
	
	public function find_all_as_array_by_columns($arr_columns_and_values, $append_sql = null)
	{
		$sql = 'SELECT * FROM "' . $this->get_table_name() . '" WHERE ';
	
		$args = array();
		
		foreach($arr_columns_and_values as $column_name => $value)
			$args[] = $column_name . ' = ?';
	
		$sql .= implode(' AND ',$args);
		
		if($append_sql)
			$sql .= ' ' . $append_sql;
		
		return $this->_db->get_records_as_array($sql, array_values($arr_columns_and_values));
	}
	
	public function set_properties_from_record($record)
	{
		foreach(array_keys($record) as $index => $column_name)
		{
			if(isset($this->_columns[$column_name]))
			{
				switch($this->_columns[$column_name]['type'])
				{
					case 'boolean':
					case 'bool':
						$this->_columns[$column_name]['value'] = ($record[$column_name] == 't');
						break;
					default:
						$this->_columns[$column_name]['value'] = $record[$column_name];
				}
			}
		}
	}
	
	public function set_properties_from_form_post_array($arr_fields)
	{
		foreach($this->_columns as $column_name => $column)
		{
			if(in_array($column_name, $this->_protected_fields))
				continue;
			
			$is_checkbox = in_array($column['type'],array('bool','boolean','tinyint'));
			
			if($is_checkbox)
				$this->$column_name = isset($arr_fields[$column_name]);
			else if(isset($arr_fields[$column_name]))
			{
				switch($column['type'])
				{
					case 'int':
					case 'integer':
						$this->$column_name = (int)preg_replace('/[^0-9]/','',trim($arr_fields[$column_name]));
					default:
						if(isset($arr_fields[$column_name]))
							$this->$column_name = trim($arr_fields[$column_name]);
				}
			}
		}		
	}
	
	/**
	 * Returns object properties as an associative array
	 */
	public function to_array()
	{
		$result_array = array();
		
		foreach($this->_columns as $column => $data)
			$result_array[$column] = $data['value'];
			
		return $result_array;	
	}
	
	public function is_column_a_foreign_key($column_name)
	{
		return isset($this->_columns[$column_name]['foreign_key']);
	}
	
	/**
	 * Loads a foreign key and sets it as a property of this object
	 */
	public function load_foreign_key($fk_column_name)
	{			
		$foreign_table_name 	= $this->_columns[$fk_column_name]['foreign_key']['table'];
		$foreign_column_name 	= $this->_columns[$fk_column_name]['foreign_key']['column'];

		if(isset($this->_foreign_table_objects[$foreign_table_name]))
			return $this->_foreign_table_objects[$foreign_table_name];
		
		$obj_foreign_table = ActiveRecord::load($foreign_table_name);
		
		if(!$obj_foreign_table->find_by_columns(array($foreign_column_name => $this->{$fk_column_name})))
			return false;
			
		$this->_foreign_table_objects[$foreign_table_name] = $obj_foreign_table;
		
		return $obj_foreign_table;
	}
	
	/**
	 * Returns true if this is a new record that is not yet in the database, else false
	 */	
	public function is_new()
	{
		return is_null($this->_columns[$this->_primary_key]['value']);
	}	
	
	/**
	 * Returns true if this table contains a primary key field, else false
	 */
	public function has_primary_key()
	{
		return !is_null($this->_primary_key);
	}
		
	/**
	 * Saves the current record, inserts if new else updates
	 */
	public function save()
	{
		if($this->is_new())
			return $this->insert();
		else
			return $this->update();
	}	
	
	public function before_insert(){}
	public function before_update(){}
	public function before_save_after_validate(){}
	public function after_insert(){}
	public function after_save(){}
	public function after_update(){}
	
	/**
	 * Inserts a new record into the database
	 */
	public function insert()
	{
		$this->before_validate();
		$this->before_insert();
		
		if($this->validate())
		{
			$this->before_save_after_validate();
			
			$sql = 'INSERT INTO "' . $this->get_table_name() . '" (' . implode(',',array_keys($this->_columns)) . ') VALUES(';
			
			$values 	= array();
			$sql_parts 	= array();
			
			foreach($this->_columns as $column => $meta)
			{
				if(isset($meta['primary_key']))
					$sql_parts[] = 'DEFAULT';
				else if(is_null($meta['value']))
				{
					if(isset($meta['default_value']))
						$sql_parts[] = $meta['default_value'];
					else
						$sql_parts[] = 'NULL';
				}
				else	
				{
					$sql_parts[] 	= '?';
					$values[] 		= $meta['value'];
				}
			}
			
			$sql .= implode(',',$sql_parts) . ')';
					
			$this->_db->query($sql, $values);	

			$this->_columns[$this->_primary_key]['value'] = $this->_db->get_last_insert_id($this->get_table_name(), $this->_primary_key);
			$this->after_insert();
			$this->after_save();
				
			return $this->_columns[$this->_primary_key]['value'];
		}
		else
			return false;
	}

	/**
	 * Returns true if the column value is empty
	 * @see http://php.net/empty
	 */	
	public function is_column_empty($column_name)
	{
		return empty($this->_columns[$column_name]['value']);	
	}
	
	/**
	 * Sets the value of a column to param $value if it is currently empty
	 * @see http://php.net/empty
	 */
	protected function set_default_value($column_name, $value)
	{
		if($this->is_column_empty($column_name))
			$this->{"set_$column_name"}($value);
	}
	
	/**
	 * Updates the existing db record
	 */
	public function update()
	{
		$this->before_validate();
		$this->before_update();
				
		if($this->validate())
		{
			$this->before_save_after_validate();
			
			$sql = 'UPDATE "' . $this->get_table_name() . '" SET ';
			
			$values 		= array();
			$column_sets 	= array();
			
			foreach($this->_columns as $column => $meta)
			{
				$column_sets[] = '"' . $column . '" = ?';
				
				$values[] = $meta['value'];
			}
			
			$values[] = $this->_columns[$this->_primary_key]['value'];
			
			$sql .= implode(',',$column_sets);
			$sql .= ' WHERE ' . $this->_primary_key . ' = ?';
		
			$this->_db->query($sql, $values);			
			
			$this->after_update();
			$this->after_save();
			
			return true;
		}
		else
			return false;		
	}
	
	public function delete()
	{
		$sql = 'DELETE FROM "' . $this->get_table_name() . '" WHERE ' . $this->_primary_key . ' = ?';
		
		$this->_db->query($sql, array($this->_columns[$this->_primary_key]['value']));
		
		return true;
	}
		
	public function get_table_name()
	{
		return DB_TABLE_PREFIX . get_class($this);
	}
	
	static function &load($model_name)
	{
		self::require_model($model_name);
			
		$model_obj = new $model_name();
		
		return $model_obj;
	}
	
	static function require_model($model_name)
	{
		if(!class_exists($model_name))
		{
			$model_path_and_filename = MODELS_DIR . '/' . $model_name . '.php';
			
			if(!file_exists($model_path_and_filename))
				throw new ActiveRecordException('Model file ['.$model_name.'] not found');
				
			require($model_path_and_filename);
			
			if(!class_exists($model_name))
				throw new ActiveRecordException('Class ['.$model_name.'] does not exist in model file, please update your models class name');
		}
	}
	
	/**
	 * Callback method called right before validate method, which is on validate, save, insert, update
	 */
	public function before_validate(){}
	
	/**
	 * Checks if the model has thrown any errors
	 */
	public function validate()
	{		
		if(count($this->_errors))
			return false;
		else
			return true;
	}
	
	/**
	 * Validates that this column has been set to value
	 * @param string $column Name of the column to validate
	 * @return boolean true if value present, else false
	 */
	public function validates_presence_of($column, $msg = null)
	{
		if(is_null($this->_columns[$column]['value']) || $this->_columns[$column]['value'] === '')
			$this->add_error($msg ? $msg : (ucfirst(str_replace('_',' ',$column)) . ' cannot be empty'));
		else
			return true;
			
		return false;
	}
	
	/**
	 * Validates the uniqueness of a column
	 */
	public function validates_uniqueness_of($column, $msg = null)
	{
		$sql 	= 'SELECT 1 FROM ' . $this->get_table_name() . ' WHERE ';
		$values = array();
		
		if(is_array($column))
		{
			$sql_parts 	= array();
			
			foreach($column as $column_name)
			{
				$sql_parts[] 	= ' ' . $column_name . ' = ?';
				$values[] 		= $this->_columns[$column_name]['value'];
			}
				
				
			$sql .= implode(' AND ', $sql_parts);
		}
		else
		{
			$sql 		.= $column . ' = ?';
			$values[] 	= $this->_columns[$column]['value'];
		}
		
		//Make sure we dont check on our own record
		if($this->_columns[$this->_primary_key]['value'])
			$sql .= ' AND ' . $this->_primary_key . ' <> ' . $this->_columns[$this->_primary_key]['value'];
		
		if($this->_db->get_first_cell($sql, $values))
			$this->add_error($msg ? $msg : (ucfirst(str_replace('_',' ',$column)) . ' is not unique'));
		else
			return true;
			
		return false;		
	}
	
	public function validates_length_of($column, $min_length, $max_length = null, $msg)
	{
		$column_length = strlen($this->{$column});
		
		if($max_length && ($column_length < $min_length || $column_length > $max_length))
		{	
			$this->add_error($msg);
			return false;
		}
		else if($column_length < $min_length)
		{
			$this->add_error($msg);
			return false;
		}
		
		return true;
	}

	/**
	 * Validates that a foriegn key field points to a valid record in another table
	 * @param string $field name of field to valilate against
	 * @param string $message Message to generate on failure (optional)
	 * @return boolean true if foriegn key is valid, else false
	 */
	public function validates_foriegnkey_exists($field, $message = null)
	{
		if(isset($this->_columns[$field]['foreign_key']))
		{
			$foriegn_table 			= $this->_columns[$field]['foreign_key']['table'];
			$foreign_table_column 	= $this->_columns[$field]['foreign_key']['column'];
			$foriegn_table_obj 		= ActiveRecord::load($foriegn_table);
			
			if($foriegn_table_obj->find_by_columns(array($foreign_table_column => $this->$field)))
				return true;
		}
		
		$this->add_error(is_null($message) ? ($field.' foriegn key is not valid') : $message);		
			
		return false;
	}	
		
	public function validates_regular_expression_of($column, $regex, $msg)
	{
		if(!preg_match($regex, $this->_columns[$column]['value']))
			$this->add_error($msg);
		else
			return true;	
	}
	
	/**
	 * Add an error string to the error array
	 */
	public function add_error($error)
	{
		$this->_errors[] = $error;
	}
	
	/**
	 * Returns boolean true if there have been no errors raised, else false
	 */
	public function is_valid()
	{
		return empty($this->_errors);
	}
	
	public function get_errors()
	{
		return $this->_errors;
	}
	
	public function clear_all_errors()
	{
		$this->_errors = array();
	}
	
	public function begin_transaction()
	{
		$this->_db->query('BEGIN');
	}
	
	public function commit_transaction()
	{
		$this->_db->query('COMMIT');
	}
	
	public function rollback_transaction()
	{
		$this->_db->query('ROLLBACK');
	}
	
	/**
	 * Retrives a field list of type-value for generating a form
	 * @return array field => (value,table,type) 
	 * @see to_array()
	 */
	public function get_fields_for_form()
	{
		$result = array();

		foreach($this->_columns as $column_name => $column_data)
		{
			$field_value = !is_null($column_data['value']) ? $column_data['value'] : (isset($column_data['default_value']) ? $column_data['default_value'] : '');
			
			//If the field is not a foriegn key (cannot be converted into form field)
			if(empty($column_data['foreign_key']) && !in_array($column_name, $this->_protected_fields))
				$result[$column_name] = array_merge($column_data, array('table' => $this->get_table_name(), 'value' => $field_value, 'null' => !$column_data['not_null']));	
		}

		return $result;
	}
}
?>
