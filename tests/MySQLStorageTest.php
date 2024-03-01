<?php
require_once __DIR__.'/../StorageInterface.php';
require_once __DIR__.'/../MySQLStorage.php';
require_once __DIR__.'/../vendor/autoload.php';

use PHPUnit\Framework\TestCase;

/**
 * MySQLStorageTest
 *
 * A suite of test cases for the MySQLStorage class to ensure correct storage of data into db
 */
class MySQLStorageTest extends TestCase {
    private $dbConfig = [
        'host' => 'localhost:3306',
        'database' => 'kaufland',
        'username' => 'root',
        'password' => '',
    ];

    private $storage;

    // Set up the database connection before each test
    protected function setUp(): void {
        parent::setUp();

        $this->storage = new MySQLStorage();
        $this->storage->connect(
            $this->dbConfig['host'],
            $this->dbConfig['database'],
            $this->dbConfig['username'],
            $this->dbConfig['password']
        );

        $this->assertInstanceOf(PDO::class, $this->getPrivateProperty($this->storage, 'db'));
    }

    // Close the database connection after each test
    // Drop the test table if it exists
    protected function tearDown(): void {
        $tableName = 'test_table';
        $this->dropTableIfExists($tableName);

        $this->storage->close();

        parent::tearDown();
    }

    // Drop table if exists
    private function dropTableIfExists($tableName) {
        try {
           $this->storage->getDb()->exec("DROP TABLE IF EXISTS $tableName");
        } catch (Exception $e) {
            $this->fail('Exception thrown during table deletion: ' . $e->getMessage());
        }
    }

    // Insert test data in db
    public function testInsertData() {
        $columns = [
            'column1',
            'column2',
        ];

        $data = [
            [
                'column1' => 'value1',
                'column2' => 'value2',
            ]
        ];

        $tableName = 'test_table';

        try {
            $this->storage->insertData($data, $tableName, $columns);
    
            $fetchResult = $this->fetchDataFromDatabase($tableName, $data, $this->storage->getDb());
            $this->assertNotEmpty($fetchResult, 'Data should be present in the database after insertion');    
    
        } catch (Exception $e) {
            $this->fail('Exception thrown: ' . $e->getMessage());
        }
    }

    // Fetch data from db
    public function fetchDataFromDatabase($tableName, $expectedData, $db) {
        $stmt = $db->prepare("SELECT * FROM $tableName WHERE column1 = :column1 AND column2 = :column2");
        $stmt->bindParam(':column1', $expectedData['column1']);
        $stmt->bindParam(':column2', $expectedData['column2']);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Helper method to access private properties
    private function getPrivateProperty($object, $propertyName) {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);

        return $property->getValue($object);
    }

}

?>