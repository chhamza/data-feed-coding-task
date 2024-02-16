<?php

class Program {
    private $storage;
    private $logFilePath = '';
    private $nestedData = '';

    public function __construct(StorageInterface $storage, string $errorLogFile) {
        $this->storage = $storage;
        $this->logFilePath = $errorLogFile;
    }

    // Process XML file and store data
    public function run($xmlFilePath) {
        try {

            // Load XML file
            $xml = simplexml_load_file($xmlFilePath);

            $filenameWithoutExtension = pathinfo($xmlFilePath, PATHINFO_FILENAME);

            // Process and insert data into storage
            echo 'Processing...' . PHP_EOL;

            // Iterate through all elements within element e.g. <item>
            foreach ($xml->children() as $element) {
                $data = [];

                // Check if element has attributes
                if ($element->attributes()) {
                    foreach ($element->attributes() as $attribute) {
                        $data[$attribute->getName()] = (string)$attribute;
                    }
                }

                // Iterate through all child elements of element
                foreach ($element->children() as $el) {
                    $elementName = $el->getName();

                    // Check if the element has attributes
                    if ($el->attributes()) {
                        // Iterate through attributes and add them to the data array
                        foreach ($el->attributes() as $attribute) {
                            $data["$elementName" . "_" . $attribute->getName()] = (string)$attribute;
                        }
                    } else {
                        if ($el->count() > 0) {
                            // if nested children are more than 0
                            $data["$elementName"] = $this->processNestedElement($el);
                        } else {
                            $data["$elementName"] = (string)$el;
                        }
                    }
                }

                $this->storage->insertData($data, $filenameWithoutExtension);
            }

            echo "Data successfully inserted into the storage." . PHP_EOL;
        } catch (\SimpleXMLElement $e) {
            $this->logError('Invalid XML file: ' . $e->getMessage());
            return; // Return early if XML is invalid
        } catch (Exception $e) {
            $this->logError('Storage Error: ' . $e->getMessage());
        } finally {
            // Close the storage connection
            $this->storage->close();
        }
    }

    // Recursive function to process nested elements
    private function processNestedElement($element) {
        foreach ($element->children() as $nestedElement) {
            $nestedElementName = $nestedElement->getName();
    
            if ($nestedElement->count() > 0) {
                $this->nestedData .= $this->processNestedElement($nestedElement) . ', ';
            } else {
                $this->nestedData .= (string)$nestedElement . ', ';
            }
        }

        // Remove the trailing comma and space
        $this->nestedData = rtrim($this->nestedData, ', ');
        $this->nestedData .= '. ';

        return $this->nestedData;
    }

    // Append errors in log file
    private function logError($message) {
        file_put_contents($this->logFilePath, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND);
    }
}

?>