<?php
require_once '../DatabaseConfig.php';
use PHPUnit\Framework\TestCase;

class DatabaseConfigTest extends TestCase
{
    public function testGetHost()
    {
        $config = new DatabaseConfig('localhost', 'mydb', 'user', 'pass');
        $this->assertEquals('localhost', $config->getHost());
    }

    public function testGetDatabase()
    {
        $config = new DatabaseConfig('localhost', 'mydb', 'user', 'pass');
        $this->assertEquals('mydb', $config->getDatabase());
    }

    public function testGetUsername()
    {
        $config = new DatabaseConfig('localhost', 'mydb', 'user', 'pass');
        $this->assertEquals('user', $config->getUsername());
    }

    public function testGetPassword()
    {
        $config = new DatabaseConfig('localhost', 'mydb', 'user', 'pass');
        $this->assertEquals('pass', $config->getPassword());
    }
}
