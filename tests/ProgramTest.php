<?php
require_once __DIR__.'/../StorageInterface.php';
require_once __DIR__.'/../MySQLStorage.php';
require_once __DIR__.'/../Program.php';
require_once __DIR__.'/../vendor/autoload.php';

use PHPUnit\Framework\TestCase;

/**
 * ProgramTest
 *
 * A suite of test cases for the Program class to ensure correct initialization,
 * processing of XML file, and storage of data.
 */
class ProgramTest extends TestCase {

    // Tests valid initialization of a Program object with a StorageInterface instance and a log file path.
    public function testValidInitialization() {
        $storageMock = $this->createMock(StorageInterface::class);
        $program = new Program($storageMock, 'error.log');
        $this->assertInstanceOf(Program::class, $program);
    }

    // Tests processing of a valid XML file and insertion of data into storage.
    public function testRunValidXmlFile() {
        $storageMock = $this->createMock(StorageInterface::class);
        $program = new Program($storageMock, 'error.log');

        $xmlFilePath = './xml_files_test/valid.xml';
        $storageMock->expects($this->once())
            ->method('insertData')
            ->with(
                $this->callback(function ($data) {
                    $expectedData = [
                        'attribute1' => 'value1',
                        'attribute2' => 'value2',
                        'child1' => 'value3',
                        'child2' => 'value4',
                        'child3' => 'value5',
                        'child4' => 'value6',
                    ];
                    return $data == $expectedData;
                }),
                'valid',
                $this->callback(function ($data) {
                    $expectedColumns = [
                        'attribute1',
                        'attribute2',
                        'child1',
                        'child2',
                        'child3',
                        'child4',
                    ];
                    return $columns == $expectedColumns;
                })
            );

        $program->run($xmlFilePath);
    }

    // Tests processing of an invalid XML file and logging of the error.
    public function testRunInvalidXmlFile() {
        $storageMock = $this->createMock(StorageInterface::class);
        $program = new Program($storageMock, 'error.log');

        $xmlFilePath = './xml_files_test/invalid.xml';
        $storageMock->expects($this->never())
            ->method('insertData');

        $program->run($xmlFilePath);

        $logContents = file_get_contents('error.log');
        $this->assertStringContainsString('Storage Error', $logContents);
    }

    // Tests processing of an XML file with nested elements and storage of nested data.
    public function testRunXmlFileWithNestedElements() {
        $storageMock = $this->createMock(StorageInterface::class);
        $program = new Program($storageMock, 'error.log');

        $xmlFilePath = './xml_files_test/nested.xml';
        $storageMock->expects($this->once())
            ->method('insertData')
            ->with(
                $this->callback(function ($data) {
                    $expectedData = [
                        'attribute1' => 'value1',
                        'attribute2' => 'value2',
                        'child1' => 'value3',
                        'child2' => 'value4',
                        'nestedChild1' => 'value5, value6. '
                    ];
                    return $data == $expectedData;
                }),
                'nested',
                $this->callback(function ($data) {
                    $expectedColumns = [
                        'attribute1',
                        'attribute2',
                        'child1',
                        'child2',
                        'nestedChild1',
                    ];
                    return $columns == $expectedColumns;
                })
            );

        $program->run($xmlFilePath);
    }
}

?>