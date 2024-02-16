<?php

class App {
    private $storage;
    private $program;
    private $dbConfig;

    public function __construct(StorageInterface $storage, Program $program, DatabaseConfig $dbConfig) {
        $this->storage = $storage;
        $this->program = $program;
        $this->dbConfig = $dbConfig;
    }

    public function setupAndProcess($xmlFilePath) {
        $this->storage->connect(
            $this->dbConfig->getHost(),
            $this->dbConfig->getDatabase(),
            $this->dbConfig->getUsername(),
            $this->dbConfig->getPassword()
        );

        $this->program->run($xmlFilePath);

        $this->storage->close();
    }
}

?>