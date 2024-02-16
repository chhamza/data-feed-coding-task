<?php

require_once 'StorageInterface.php';
require_once 'MySQLStorage.php';
require_once 'Program.php';
require_once 'DatabaseConfig.php';
require_once 'App.php';

// Database configuration
$host = 'localhost:3306';
$database = 'kaufland';
$username = 'root';
$password = '';
$dbConfig = new DatabaseConfig($host, $database, $username, $password);

// Specify XML File
$xmlFile = "./xml_files/feed.xml";

// Specify error log File
$errorLogFile = "error.log";

// Usage with MySQL storage
$mysqlStorage = new MySQLStorage();
$program = new Program($mysqlStorage, $errorLogFile);
$app = new App($mysqlStorage, $program, $dbConfig);
$app->setupAndProcess($xmlFile);

?>