<?php

interface StorageInterface
{
    public function connect($host, $username, $password, $database);

    public function insertData(array $data, string $rootElementName);

    public function close();
}

?>