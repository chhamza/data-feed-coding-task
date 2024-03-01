<?php

use JsonMachine\Items;
use JsonMachine\JsonDecoder\PassThruDecoder;
use JsonMachine\JsonDecoder\ErrorWrappingDecoder;
use JsonMachine\JsonDecoder\ExtJsonDecoder;

require_once __DIR__.'/vendor/autoload.php';

class Program {
    private $storage;
    private $logFilePath = '';
    private $nestedData = '';
    private const CHUNK_SIZE = 1000;
    private $jsonRowData = []; 

    public function __construct(StorageInterface $storage, string $errorLogFile) {
        $this->storage = $storage;
        $this->logFilePath = $errorLogFile;
    }

    // Process files and store data
    public function run($filePath) {
        try {
            // Load file based on its extension
            $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
            switch ($fileExtension) {
                case 'xml':
                    $this->processXmlFile($filePath);
                    break;
                case 'json':
                    $this->processJsonFile($filePath);
                    break;
                case 'csv':
                    $this->processCsvFile($filePath);
                    break;
                default:
                    throw new Exception('Unsupported file format: ' . $fileExtension);
            }

            echo "Data successfully inserted into the storage." . PHP_EOL;
        } catch (\Exception $e) {
            $this->logError('Storage Error: ' . $e->getMessage());
        } finally {
            // Close the storage connection
            $this->storage->close();
        }
    }

    // XML
    function exceptionErrorHandler($severity, $message, $file, $line) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
    
    private function processXmlFile($xmlFilePath, $startNodePosition = 0) {
        $formattedFilePath = $this->formatXml($xmlFilePath);

        $reader = new XMLReader();
        $batchData = [];
        $columns = [];
        $currentNodePosition = 0;

        set_error_handler([$this, 'exceptionErrorHandler']);

        try {
            $reader->open($formattedFilePath);
    
            while ($reader->read()) {
                if ($reader->nodeType == XMLReader::ELEMENT) {
                    $currentNodePosition++; 

                    if ($currentNodePosition >= $startNodePosition) {
    
                        try {
                            $data = [];
        
                            $element = new SimpleXMLElement($reader->readOuterXML());
                            $filenameWithoutExtension = pathinfo($formattedFilePath, PATHINFO_FILENAME);
    
                            foreach ($element->attributes() as $attributeName => $attributeValue) {
                                $data[$attributeName] = (string)$attributeValue;
                            }
        
                            foreach ($element->children() as $child) {
                                $this->processChildElement($child, $data);
                                $columns = array_keys($data);
                                $batchData[] = array_values($data);
                            }
                            print_r($columns);
                            print_r($batchData);
                            $this->storage->insertData($batchData, $filenameWithoutExtension, $columns);
        
                        } catch (Exception $e) {
                            // Log the error and continue with the next element
                            $this->logError('Error processing element: ' . $e->getMessage());

                            // Currently handle only missing closing tags
                            preg_match('/line (\d+)/', $e->getMessage(), $lineMatches);
                            preg_match('/tag mismatch: (\w+)/', $e->getMessage(), $tagMatches);

                            if (!empty($lineMatches[1]) && !empty($tagMatches[1])) {
                                $lineNumber = $lineMatches[1];
                                $missingTag = $tagMatches[1];
                                
                                try {
                                    $fixedFilePath = $this->insertMissingClosingTagAtLine($formattedFilePath, $lineNumber, $missingTag);
                                    if ($fixedFilePath) {
                                        $this->processXmlFile($fixedFilePath, $startNodePosition);
                                        return; // Exit the current processing to avoid duplicate handling
                                    }
                                } catch (Exception $e) {
                                    echo "An error occurred: " . $e->getMessage() . "\n";
                                }
                            }
                        }

                        $reader->next();
                    }
                }
            }
        } catch (Exception $e) {
            $this->logError('Error processing XML file: ' . $e->getMessage());
          
            // Currently handle only missing closing tags
            preg_match('/line (\d+)/', $e->getMessage(), $lineMatches);
            preg_match('/tag mismatch: (\w+)/', $e->getMessage(), $tagMatches);

            if (!empty($lineMatches[1]) && !empty($tagMatches[1])) {
                $lineNumber = $lineMatches[1];
                $missingTag = $tagMatches[1];
                
                try {
                    $fixedFilePath = $this->insertMissingClosingTagAtLine($formattedFilePath, $lineNumber, $missingTag);
                    if ($fixedFilePath) {
                        $this->processXmlFile($fixedFilePath, $startNodePosition);
                        return; // Exit the current processing to avoid duplicate handling
                    }
                } catch (Exception $e) {
                    echo "An error occurred: " . $e->getMessage() . "\n";
                }
            }
        } finally {
            $reader->close();

            // Delete the formatted file after processing is complete
            if (file_exists($formattedFilePath)) {
                unlink($formattedFilePath);
            }
        }
    }

    function insertMissingClosingTagAtLine($filePath, $errorLine, $missingTag) {
        $lines = file($filePath);
        if ($lines === false) {
            throw new Exception("Failed to read file: $filePath");
        }
    
        // Check if the specified line is within the file
        if (count($lines) >= $errorLine - 1) {
            // Insert the missing closing tag before the line where the error was detected
            $lines[$errorLine - 1] = rtrim($lines[$errorLine - 1]) . "</$missingTag>\n";
        } else {
            throw new Exception("Error line number exceeds the file's total number of lines.");
        }
    
        $filenameWithoutExtension = pathinfo($filePath, PATHINFO_FILENAME);
        $fixedFilePath = $filenameWithoutExtension . '_fixed.xml';
        $result = file_put_contents($fixedFilePath, implode('', $lines));
        if ($result === false) {
            throw new Exception("Failed to write the repaired XML back to the file.");
        }
    
        return $fixedFilePath; 
    }

    function formatXml($originalFilePath) {
        // Create a copy of the original file
        $pathInfo = pathinfo($originalFilePath);
        $copyFilePath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_copy.' . $pathInfo['extension'];
        if (!copy($originalFilePath, $copyFilePath)) {
            throw new Exception("Failed to create a copy of the original file.");
        }

        return $copyFilePath;
    }
   
    private function processChildElement($child, &$data, $prefix = '') {

        $childName = $prefix . $child->getName();
    
        if ($child->attributes()) {
            foreach ($child->attributes() as $attribute) {
                $data["$childName" . "_" . $attribute->getName()] = (string)$attribute;
            }
        }
    
        if ($child->count() > 0) {
            foreach ($child->children() as $nestedChild) {
                $this->processChildElement($nestedChild, $data, $childName . '_');
            }
        } else {
            $data[$childName] = (string)$child;
        }
    }

    //Recursive function to process nested elements
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


    // JSON
    private function processJsonFile($jsonFilePath, $batchSize = 1000) {
        try {
            $filenameWithoutExtension = pathinfo($jsonFilePath, PATHINFO_FILENAME);
            $tempFiles = $this->splitJsonToTempFiles($jsonFilePath);
            $this->processJsonChunks($tempFiles, $filenameWithoutExtension);
    
        } catch (Exception $e) {
            $this->logError('Error processing JSON file: ' . $e->getMessage());
        }
    }

    function splitJsonToTempFiles($jsonFilePath) {
        $fileHandle = fopen($jsonFilePath, 'rb');
        if ($fileHandle === false) {
            throw new Exception("Cannot open file: $jsonFilePath");
        }

        $tempFiles = [];
        $buffer = '';
        $chunk = '';  
        $chunkCounter = 0;
        $insideObject = false;
        $objectDepth = 0; 
        $pathInfo = pathinfo($jsonFilePath);

        while (!feof($fileHandle)) {
            // Adjust chunk size as needed
            $buffer .= fread($fileHandle, 8192); 

            // Process the buffer character by character
            for ($i = 0; $i < strlen($buffer); $i++) {
                $char = $buffer[$i];

                // Detect the start of a JSON object
                if ($char === '{') {
                    $insideObject = true;
                    $objectDepth++; // Increase depth level
                }

                // Append character to chunk if inside a JSON object
                if ($insideObject) {
                    $chunk .= $char;
                }

                // Detect the end of a JSON object
                if ($char === '}') {
                    $objectDepth--; // Decrease depth level

                    // Check if we've reached the top-level object end
                    if ($objectDepth === 0) {
                        $insideObject = false;
                        $chunkCounter++;

                        // Write the chunk to a temporary file only if it's not empty
                        if (trim($chunk) !== '') {
                            $tempFileName = $pathInfo['dirname'] . '/' . "/json_chunk_{$chunkCounter}" . $pathInfo['extension'];
                            // print_r($tempFileName);   
                            file_put_contents($tempFileName, $chunk);
                            $tempFiles[] = $tempFileName;
                        }

                        $chunk = ''; // Reset the chunk for the next JSON object
                    }
                }
            }

            // If still inside an object, keep the chunk for the next read; otherwise, clear the buffer
            $buffer = $insideObject ? $chunk : '';
        }

        fclose($fileHandle);
        return $tempFiles;
    }
    
    function processJsonChunks($tempFiles, $tablename) {
        // Array to hold all collected data from all files
        $allData = [];  
        $headers = [];
    
        foreach ($tempFiles as $tempFile) {
            $jsonContent = file_get_contents($tempFile);
    
            if (!empty($jsonContent)) {
                $decodedJson = json_decode($jsonContent, TRUE);
    
                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedJson)) {
                    // Iterate over each top-level element in the JSON
                    foreach ($decodedJson as $topLevelKey => $topLevelValue) {
                        if (is_array($topLevelValue)) {
                            foreach ($topLevelValue as $record) {
                                if (is_array($record)) {
                                    $rowData = [];
                                    // Flatten the record if necessary and collect headers
                                    foreach ($record as $key => $value) {
                                        if (is_array($value)) {
                                            // If the value is an array, flatten it
                                            foreach ($value as $subKey => $subValue) {
                                                $flattenedKey = $subKey;  // Create a flattened key
                                                $rowData[$flattenedKey] = $subValue;
                                                // Collect flattened key as header if not already collected
                                                if (!in_array($flattenedKey, $headers)) {
                                                    $headers[] = $flattenedKey;
                                                }
                                            }
                                        } else {
                                            // Directly use the value and collect key as header
                                            $rowData[$key] = $value;
                                            if (!in_array($key, $headers)) {
                                                $headers[] = $key;
                                            }
                                        }
                                    }
                                    // Add the processed record to allData
                                    $allData[] = $rowData;
                                }
                            }
                        }
                    }
                } else {
                    // Handle JSON decoding error for this file
                }
            }
    
            // Delete the temporary file after processing
            unlink($tempFile);
        }

        // After processing all files, check and use allData and headers
        if (!empty($allData)) {
            print_r($allData);  // For debugging, see all accumulated data
            print_r($headers);  // For debugging, see all collected headers
    
            // Uncomment below line to insert data into database
            $this->storage->insertData($allData, $tablename, $headers);  // Adjust 'your_table_name' as needed
        }
    }


    // CSV
    private function processCsvFile($csvFilePath, $batchSize = 4000) {
        try {
            $filenameWithoutExtension = pathinfo($csvFilePath, PATHINFO_FILENAME);
    
            if (($handle = fopen($csvFilePath, "r")) !== FALSE) {
                // Read the first row as headers
                $headers = fgetcsv($handle, 0, ",");
                $headers = array_map(function($header) { 
                    return str_replace(' ', '', $header); 
                }, $headers);
    
                // Array to hold data for the current batch
                $batchData = []; 
    
                while (($row = fgetcsv($handle, 0, ",")) !== FALSE) {
                    // Check if row is not malformed
                    if (!is_array($row) || count($row) != count($headers)) {
                        // Skip malformed rows
                        continue; 
                    }
    
                    // Combine headers with row values
                    $rowData = array_combine($headers, $row);
                     // Add the processed row to the current batch
                    $batchData[] = $rowData;
    
                    // Check if the batch is full
                    if (count($batchData) >= $batchSize) {
                        // Insert the batch data into the database
                        $this->storage->insertData($batchData, $filenameWithoutExtension, $headers);
                        // Reset batch data after insertion
                        $batchData = []; 
                    }
                }
    
                // Insert any remaining data in the batch
                if (!empty($batchData)) {
                    $this->storage->insertData($batchData, $filenameWithoutExtension, $headers);
                }
    
                fclose($handle);
            }
        } catch (Exception $e) {
            $this->logError('Error processing CSV file: ' . $e->getMessage());
        }
    }
   
    // Append errors in log file
    private function logError($message) {
        file_put_contents($this->logFilePath, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND);
    }
}
?>