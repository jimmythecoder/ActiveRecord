<?php
	require ACTIVE_RECORD_DIR . '/active_record.php';
	require ACTIVE_RECORD_DIR . '/active_record_exception.php';
	require ACTIVE_RECORD_DIR . '/db.php';

	$database_config_file 			= parse_ini_file(CONFIG_DIR . '/database.ini',true);
	$database_config 				= $database_config_file[ENVIRONMENT];
   	$db_driver_path_and_filename 	= ACTIVE_RECORD_DIR . '/' . $database_config['adapter'] . '_db.php';
   	$db_driver_class_name 			= $database_config['adapter'] . '_db';
   	
    if(file_exists($db_driver_path_and_filename))
    	require $db_driver_path_and_filename;
    else
    	throw new Exception('Unsupported database driver in config');
    
	$DB = new $db_driver_class_name($database_config);
?>
