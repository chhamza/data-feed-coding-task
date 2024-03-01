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
    public function insertData(array $data, string $tableName, array $columns) {
        // Dynamically create columns if not existing
        $this->createColumnsIfNotExists($columns, $tableName);

        // Insert data into the table
        $this->insertDataIntoTable($tableName, $data, $columns);
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
    private function insertDataIntoTable($tableName, array $data, array $col) {
        $colString = implode(', ', $col);

        for ($i = 0; $i < count($data); $i++) {
            $row = $data[$i];
            // Ensure we're getting values from the current row
            $strVal = array_values($row);
            // Generate placeholders dynamically based on the row's data
            $placeholders = implode(', ', array_fill(0, count($strVal), '?'));
        
            try {
                // Prepare the SQL statement with dynamic placeholders
                $stmt = $this->db->prepare("INSERT INTO $tableName ($colString) VALUES ($placeholders)");
        
                // Execute with the current row's values
                $stmt->execute($strVal);
            } catch (PDOException $e) {
                // Handle the error properly
                echo "Error: " . $e->getMessage();
                break; // Optional: break the loop on error
            }
        }

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