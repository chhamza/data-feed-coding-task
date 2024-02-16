<?php

class MySQLStorage implements StorageInterface {
    private $db;

    // Connect with db
    public function connect($host, $database, $username, $password) {
        try {
            $this->db = new PDO("mysql:host=$host;dbname=$database", $username, $password);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw new Exception('Connection failed: ' . $e->getMessage());
        }
    }

    // Get db field
    public function getDb() {
        return $this->db;
    }

    // Inserts data to db
    public function insertData(array $data, string $tableName) {
        // Dynamically create columns if not existing
        $this->createColumnsIfNotExists(array_keys($data), $tableName);

        // Insert data into the table
        $this->insertDataIntoTable($tableName, $data);
    }

    // Dynamically create columns if not existing
    private function createColumnsIfNotExists(array $columns, string $tableName) {
        // Check if the table '$tableName' exists
        $stmt = $this->db->query("SHOW TABLES LIKE '$tableName'");
        $tableExists = $stmt->rowCount() > 0;

        // If the table doesn't exist, create it
        if (!$tableExists) {
            $this->createItemsTable($columns, $tableName);
        } else {
            // Check if each column exists, create it if not
            foreach ($columns as $column) {
                $stmt = $this->db->query("SHOW COLUMNS FROM $tableName LIKE '$column'");
                $columnExists = $stmt->rowCount() > 0;

                if (!$columnExists) {
                    $this->addColumnToItemsTable($column, $tableName);
                }
            }
        }
    }

    // Create table if doesn't exist
    private function createItemsTable(array $columns, string $tableName) {
        $columnsDefinition = implode(', ', array_map(function ($column) {
            return "$column VARCHAR(255)";
        }, $columns));

        $this->db->exec("CREATE TABLE $tableName ($columnsDefinition)");
    }

    // Add column in table if doesn't exist
    private function addColumnToItemsTable($column, $tableName) {
        $this->db->exec("ALTER TABLE $tableName ADD COLUMN $column VARCHAR(255)");
    }

    // Insert data into the table
    private function insertDataIntoTable($tableName, array $data) {
        foreach ($data as $columnName => $value) {

            if (is_string($value) && strlen($value) > 255) {
                // If the string length is greater than 255, alter the table
                $this->alterTableColumnSize($tableName, $columnName, 'VARCHAR(1000)');
            }
        }

        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));


        $stmt = $this->db->prepare("INSERT INTO $tableName ($columns) VALUES ($placeholders)");
        $stmt->execute(array_values($data));
    }

    // Alter table column size if it exceed from 255 characters
    private function alterTableColumnSize($tableName, $columnName, $newType) {
        $stmt = $this->db->prepare("ALTER TABLE $tableName MODIFY COLUMN $columnName $newType");
        $stmt->execute();
    }

    // close db connection
    public function close() {
        $this->db = null;
    }
}

?>