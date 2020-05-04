<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * process_extractions_base_task_test.
 *
 * @package    tool_metadata
 * @copyright  2020 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use tool_metadata\extraction;

global $CFG;
require_once($CFG->dirroot . '/admin/tool/metadata/constants.php');
require_once($CFG->dirroot . '/admin/tool/metadata/tests/mock_metadataextractor_extractor.php');
require_once($CFG->dirroot . '/admin/tool/metadata/tests/mock_metadataextractor_extractor_two.php');
require_once($CFG->dirroot . '/admin/tool/metadata/tests/mock_process_extractions_task.php');

/**
 * process_extractions_base_task_test.
 *
 * @package    tool_metadata
 * @copyright  2020 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      tool_metadata
 */
class process_extractions_base_task_test extends advanced_testcase {

    public function setUp() {
        global $DB;

        $this->resetAfterTest();

        // Create a table for mock metadataextractor subplugin.
        $dbman = $DB->get_manager();
        $table = new \xmldb_table(\metadataextractor_mock\metadata::TABLE);
        // Add mandatory fields.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('resourcehash', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null, 'id');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'date');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timecreated');
        // Add the fields used in mock class.
        $table->add_field('author', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'subject');
        $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'description');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        $dbman->create_table($table);
    }

    public function tearDown() {
        global $DB;

        $dbman = $DB->get_manager();
        $table = new \xmldb_table(\metadataextractor_mock\metadata::TABLE);
        $dbman->drop_table($table);

        // Delete all queued adhoc tasks.
        $DB->delete_records('task_adhoc');

        // Delete all extraction records.
        $DB->delete_records('tool_metadata_extractions');

        // Delete all processing startids.
        $sql = $DB->sql_like('name', ':name') . ' AND ' . $DB->sql_equal('plugin', ':plugin');
        $params = ['name' => '%_startid', 'plugin' => 'tool_metadata'];
        $DB->delete_records_select('config_plugins', $sql, $params);
    }

    public function test_get_extractions_to_process() {

        // Create a test file from fixture.
        $fs = get_file_storage();
        $syscontext = context_system::instance();
        $filepath = __DIR__.'/fixtures/testimage.jpg';
        $filerecord = array(
            'contextid' => $syscontext->id,
            'component' => 'tool_metadata',
            'filearea'  => 'unittest',
            'itemid'    => 1,
            'filepath'  => '/images/',
            'filename'  => 'testimage.jpg',
        );
        $file = $fs->create_file_from_pathname($filerecord, $filepath);

        $extractor = new \metadataextractor_mock\extractor();
        $extractortwo = new \metadataextractor_mocktwo\extractor();

        $task = new \tool_metadata\tests\mock_process_extractions_task();
        $actual = $task->get_extractions_to_process(['mock' => $extractor, 'mocktwo' => $extractortwo]);

        // File extractions should be created for all extractors, regardless of if an extraction record exists.
        $this->assertArrayHasKey($file->get_id() . $extractor->get_name(), $actual);
        $this->assertArrayHasKey($file->get_id() . $extractortwo->get_name(), $actual);
        $fileextraction = $actual[$file->get_id() . $extractor->get_name()];
        $fileextractiontwo = $actual[$file->get_id() . $extractortwo->get_name()];

        // Extraction resource id should match the file table record.
        $this->assertEquals($file->get_id(), $fileextraction->resourceid);
        $this->assertEquals($file->get_id(), $fileextractiontwo->resourceid);

        // All file extraction records should identify which extractor was being used.
        $this->assertEquals($extractor->get_name(), $fileextraction->extractor);
        $this->assertEquals($extractortwo->get_name(), $fileextractiontwo->extractor);

        // The query result should have no extraction details if no existing extraction record.
        $this->assertNull($fileextraction->extractionid);
        $this->assertNull($fileextraction->status);
        $this->assertNull($fileextractiontwo->extractionid);
        $this->assertNull($fileextractiontwo->status);

        // Add extraction record for the file for one extractor only.
        $extraction = new \tool_metadata\extraction($file, TOOL_METADATA_RESOURCE_TYPE_FILE, $extractor);
        $extraction->save();

        $actual = $task->get_extractions_to_process(['mock' => $extractor, 'mocktwo' => $extractortwo]);
        $fileextraction = $actual[$file->get_id() . $extractor->get_name()];
        $fileextractiontwo = $actual[$file->get_id() . $extractortwo->get_name()];

        // Files with an extraction record should populate the extractor details.
        $this->assertEquals($extraction->get('id'), $fileextraction->extractionid);
        $this->assertEquals($extraction->get('status'), $fileextraction->status);
        $this->assertNull($fileextractiontwo->extractionid);
        $this->assertNull($fileextractiontwo->status);
    }

    /**
     * Test that when the queue is already full, no extractions are queued.
     */
    public function test_get_extractions_to_process_queue_full() {
        global $DB;

        // Create a test file from fixture, so we know there is something to process
        // in the {files} table.
        $fs = get_file_storage();
        $syscontext = context_system::instance();
        $filepath = __DIR__.'/fixtures/testimage.jpg';
        $filerecord = array(
            'contextid' => $syscontext->id,
            'component' => 'tool_metadata',
            'filearea'  => 'unittest',
            'itemid'    => 1,
            'filepath'  => '/images/',
            'filename'  => 'testimage.jpg',
        );
        $fs->create_file_from_pathname($filerecord, $filepath);

        $extractionlimit = 10;
        set_config('total_extraction_processes', $extractionlimit, 'tool_metadata');

        // Mock queued task.
        $record = new stdClass();
        // Adhoc task record 'classname' values have a preceding slash, see core\task\manager.
        $record->classname = '\\' . \tool_metadata\task\metadata_extraction_task::class;
        $record->component = '';
        $record->nextruntime = time();
        $record->blocking = 0;

        // Fill the queue up with mocks.
        for ($i = 0; $i < $extractionlimit; $i++) {
            $DB->insert_record('task_adhoc', $record);
        }

        $extractor = new \metadataextractor_mock\extractor();
        $extractortwo = new \metadataextractor_mocktwo\extractor();

        $task = new \tool_metadata\tests\mock_process_extractions_task();
        $actual = $task->get_extractions_to_process(['mock' => $extractor, 'mocktwo' => $extractortwo]);

        // There should be no extractions to process when the queue is full.
        $this->assertEmpty($actual);
        $this->assertIsArray($actual);
    }

    /**
     * Test that max processes setting is honoured when processing tasks.
     */
    public function test_get_extractions_to_process_max_processes() {
        $maxextractions = 20;
        set_config('max_extraction_processes', $maxextractions, 'tool_metadata');

        $filecount = $maxextractions + 1;
        $files = [];

        $fs = get_file_storage();
        $syscontext = context_system::instance();

        // Create more files than max processes starting at 1, (as we can't index a file by 0 (zero)).
        for ($i = 1; $i <= $filecount; $i++) {
            $filerecord = array(
                'contextid' => $syscontext->id,
                'component' => 'tool_metadata',
                'filearea'  => 'unittest',
                'itemid'    => $i,
                'filepath'  => '/docs/',
                'filename'  => "testfile_$i.doc",
            );
            $file = $fs->create_file_from_string($filerecord, "This is test file #$i");
            $files[$file->get_id()] = $file;
        }

        $extractor = new \metadataextractor_mock\extractor();
        $extractortwo = new \metadataextractor_mocktwo\extractor();

        $task = new \tool_metadata\tests\mock_process_extractions_task();
        unset_config('process_file_' . $extractor->get_name() . '_startid', 'tool_metadata');
        unset_config('process_file_' . $extractortwo->get_name() . '_startid', 'tool_metadata');
        $actual = $task->get_extractions_to_process(['mock' => $extractor, 'mocktwo' => $extractortwo]);

        // Expect maximum of max processes for each extractor installed and configured.
        $expected = 2 * $maxextractions;
        $this->assertCount($expected, $actual);

        $actual = $task->get_extractions_to_process(['mock' => $extractor, 'mocktwo' => $extractortwo]);
        $expected = 2 * ($filecount - $maxextractions);
        $this->assertCount($expected, $actual);
    }

    /**
     * Test that when we get the extraction to process, we set the start id
     * for the next run correctly.
     */
    public function test_get_extractions_to_process_start_id_set_correctly() {
        global $DB;

        $maxextractions = 20;
        set_config('max_extraction_processes', $maxextractions, 'tool_metadata');
        // Stagger the startid of second extractor, to test that multiple startids are tracked correctly and
        // create enough files for staggered testing.
        $staggeroffset = 3;
        $filecount = $maxextractions + $staggeroffset + 1;
        $files = [];

        $fs = get_file_storage();
        $syscontext = context_system::instance();

        // Create more files than max processes starting at 1, (as we can't index a file by 0 (zero)).
        for ($i = 1; $i <= $filecount; $i++) {
            $filerecord = array(
                'contextid' => $syscontext->id,
                'component' => 'tool_metadata',
                'filearea'  => 'unittest',
                'itemid'    => $i,
                'filepath'  => '/docs/',
                'filename'  => "testfile_$i.doc",
            );
            $file = $fs->create_file_from_string($filerecord, "This is test file #$i");
            $files[$file->get_id()] = $file;
        }

        asort($files);
        $ids = array_keys($files);

        $extractor = new \metadataextractor_mock\extractor();
        $extractortwo = new \metadataextractor_mocktwo\extractor();

        $task = new \tool_metadata\tests\mock_process_extractions_task();
        // Unset the first extractor startid to test that it starts at beginning of table.
        unset_config('process_file_' . $extractor->get_name() . '_startid', 'tool_metadata');
        set_config('process_file_' . $extractortwo->get_name() . '_startid', $ids[$staggeroffset - 1], 'tool_metadata');
        $task->get_extractions_to_process(['mock' => $extractor, 'mocktwo' => $extractortwo]);

        // The next startid should be equal to the resourceid of the last resource being processed.
        $lastidoffset = $maxextractions - 1;
        $lastprocessedid = $ids[$lastidoffset];
        $this->assertEquals($lastprocessedid, get_config('tool_metadata', 'process_file_' .
            $extractor->get_name() . '_startid'));
        $lastprocessedid = $ids[$lastidoffset + $staggeroffset];
        $this->assertEquals($lastprocessedid, get_config('tool_metadata', 'process_file_' .
            $extractortwo->get_name() . '_startid'));

        // When cyclical extraction is not enabled, startid should not reset to beginning of table when we reach the end.
        $lastid = end($ids);
        $task->get_extractions_to_process(['mock' => $extractor], false);
        $this->assertEquals($lastid, get_config('tool_metadata', 'process_file_' . $extractor->get_name() . '_startid'));

        // When cyclical extraction is enabled, startid should reset to beginning of table when we reach the end.
        $task->get_extractions_to_process(['mocktwo' => $extractortwo], true);
        $this->assertEquals(0, get_config('tool_metadata', 'process_file_' . $extractortwo->get_name() . '_startid'));
    }

    /**
     * Test processing extractions.
     */
    public function test_process_extractions() {

        // Create a test file from fixture.
        $fs = get_file_storage();
        $syscontext = context_system::instance();
        $filepath = __DIR__.'/fixtures/testimage.jpg';
        $filerecord = array(
            'contextid' => $syscontext->id,
            'component' => 'tool_metadata',
            'filearea'  => 'unittest',
            'itemid'    => 1,
            'filepath'  => '/images/',
            'filename'  => 'testimage.jpg',
        );
        $file = $fs->create_file_from_pathname($filerecord, $filepath);

        $extractor = new \metadataextractor_mock\extractor();
        $extractortwo = new \metadataextractor_mocktwo\extractor();

        $task = new \tool_metadata\tests\mock_process_extractions_task();
        $fileextractions = $task->get_extractions_to_process(['mock' => $extractor, 'mocktwo' => $extractortwo]);
        // Limit records to the test file created, to avoid processing standard moodle files in test.
        $records = [
            $fileextractions[$file->get_id() . $extractor->get_name()],
            $fileextractions[$file->get_id() . $extractortwo->get_name()]
        ];

        $status = $task->process_extractions($records, ['mock' => $extractor, 'mocktwo' => $extractortwo]);

        $this->assertEquals(0, $status->completed);
        $this->assertEquals(2, $status->queued);
        $this->assertEquals(0, $status->pending);
        $this->assertEquals(0, $status->errors);
        $this->assertEquals(0, $status->unsupported);
        $this->assertEquals(0, $status->unknown);

        // Get the updated extraction records.
        $fileextractions = $task->get_extractions_to_process(['mock' => $extractor, 'mocktwo' => $extractortwo]);
        // Limit records to the test file created, to avoid processing standard moodle files in test.
        $records = [
            $fileextractions[$file->get_id() . $extractor->get_name()],
            $fileextractions[$file->get_id() . $extractortwo->get_name()]
        ];
        $status = $task->process_extractions($records, ['mock' => $extractor, 'mocktwo' => $extractortwo]);

        $this->assertEquals(0, $status->completed);
        $this->assertEquals(0, $status->queued);
        $this->assertEquals(2, $status->pending);
        $this->assertEquals(0, $status->errors);
        $this->assertEquals(0, $status->unsupported);
        $this->assertEquals(0, $status->unknown);

        // Change the status of one extraction to an error.
        $records[0]->status = extraction::STATUS_ERROR;

        $status = $task->process_extractions($records, ['mock' => $extractor, 'mocktwo' => $extractortwo]);
        $this->assertEquals(0, $status->completed);
        $this->assertEquals(0, $status->queued);
        $this->assertEquals(1, $status->pending);
        $this->assertEquals(1, $status->errors);
        $this->assertEquals(0, $status->unsupported);
        $this->assertEquals(0, $status->unknown);
    }

    /**
     * Data provider for testing calculation of extraction limits.
     *
     * @return array
     */
    public function calculate_extraction_limit_per_extractor_provider() {
        return [
            '1 extractor, no queued tasks' => [1, 0, 300, 1000, 300],
            '2 extractors, no queued tasks' => [2, 0, 300, 1000, 300],
            '3 extractors, no queued tasks' => [3, 0, 300, 1000, 300],
            '4 extractors, no queued tasks' => [4, 0, 300, 1000, 250],
            '1 extractor, 300 queued tasks' => [1, 300, 300, 1000, 300],
            '2 extractor, 300 queued tasks' => [2, 300, 300, 1000, 300],
            '3 extractor, 300 queued tasks' => [3, 300, 300, 1000, 233],
            '4 extractor, 300 queued tasks' => [4, 300, 300, 1000, 175],
            '1 extractor, 1000 queued tasks' => [1, 1000, 300, 1000, 0],
            '2 extractor, 1000 queued tasks' => [2, 1000, 300, 1000, 0],
            '3 extractor, 1000 queued tasks' => [3, 1000, 300, 1000, 0],
            '4 extractor, 1000 queued tasks' => [4, 1000, 300, 1000, 0],
            '1 extractor, 2000 queued tasks' => [1, 2000, 300, 1000, 0],
            '2 extractor, 2000 queued tasks' => [2, 2000, 300, 1000, 0],
            '3 extractor, 2000 queued tasks' => [3, 2000, 300, 1000, 0],
            '4 extractor, 2000 queued tasks' => [4, 2000, 300, 1000, 0],
        ];
    }

    /**
     * Test that extraction limit is calculated correctly.
     *
     * @dataProvider calculate_extraction_limit_per_extractor_provider
     *
     * @param int $extractorcount the count of installed and enabled metadataextractor subplugins.
     * @param int $queuedprocesses the current count of pending adhoc tasks.
     * @param int $maxprocesses the configured admin setting max_extraction_processes value.
     * @param int $totalprocesses the configured admin setting total_extraction_processes value.
     * @param int $expectedlimit the expected limit which should result.
     */
    public function test_calculate_extraction_limit_per_extractor($extractorcount, $queuedprocesses,
                                                                  $maxprocesses, $totalprocesses, $expectedlimit) {
        global $DB;

        set_config('max_extraction_processes', $maxprocesses, 'tool_metadata');
        set_config('total_extraction_processes', $totalprocesses, 'tool_metadata');

        // Mock queued task.
        $record = new stdClass();
        // Adhoc task record 'classname' values have a preceding slash, see core\task\manager.
        $record->classname = '\\' . \tool_metadata\task\metadata_extraction_task::class;
        $record->component = '';
        $record->nextruntime = time();
        $record->blocking = 0;

        for ($i = 0; $i < $queuedprocesses; $i++) {
            $DB->insert_record('task_adhoc', $record);
        }

        $task = new \tool_metadata\tests\mock_process_extractions_task();
        $actual = $task->calculate_extraction_limit_per_extractor($extractorcount);

        $this->assertEquals($expectedlimit, $actual);
    }

    /**
     * Test getting the config name for the processing startid for an extractor.
     */
    public function test_get_startid_config_name() {

        $task = new \tool_metadata\tests\mock_process_extractions_task();

        // We're testing a private method, so we need to setup reflector magic.
        $method = new ReflectionMethod('\tool_metadata\tests\mock_process_extractions_task', 'get_startid_config_name');
        $method->setAccessible(true); // Allow accessing of private method.

        $actual = $method->invoke($task, 'mock');
        $this->assertEquals('process_file_mock_startid', $actual);

        $actual = $method->invoke($task, 'mocktwo');
        $this->assertEquals('process_file_mocktwo_startid', $actual);
    }

    /**
     * Test getting the startid for processing file extractions for specific extractor.
     */
    public function test_get_extractor_startid() {

        $task = new \tool_metadata\tests\mock_process_extractions_task();
        $extractor = new \metadataextractor_mock\extractor();
        unset_config('process_file_' . $extractor->get_name() . '_startid', 'tool_metadata');

        // We're testing a private method, so we need to setup reflector magic.
        $method = new ReflectionMethod('\tool_metadata\tests\mock_process_extractions_task', 'get_extractor_startid');
        $method->setAccessible(true); // Allow accessing of private method.
        $actual = $method->invoke($task, $extractor->get_name());

        // When no extractions have been conducted, startid should be 0 (zero).
        $this->assertEquals(0, $actual);

        $mockresources = [];
        // Create test file resources.
        for ($i = 0; $i < 10; $i++) {
            $fs = get_file_storage();
            $syscontext = context_system::instance();
            $filerecord = array(
                'author'    => 'Rick Sanchez',
                'contextid' => $syscontext->id,
                'component' => 'tool_metadata',
                'filearea'  => 'unittest',
                'itemid'    => $i,
                'filepath'  => '/',
                'filename'  => "test_$i.doc",
            );

            $file = $fs->create_file_from_string($filerecord, "This is test file #$i");
            $mockresources[$file->get_id()] = $file;
            $extraction = new \tool_metadata\extraction($file, TOOL_METADATA_RESOURCE_TYPE_FILE, $extractor);
            $extraction->set('status', extraction::STATUS_COMPLETE);
            $extraction->save();
        }

        // When there is no stored startid, the startid should be highest resourceid out of successfully processed resources of file type for extractor.
        unset_config('process_file_' . $extractor->get_name() . '_startid', 'tool_metadata');
        $resourceids = array_keys($mockresources);
        sort($resourceids);
        $expected = end($resourceids);
        $actual = $method->invoke($task, $extractor->get_name());
        $this->assertEquals($expected, $actual);

        // When there is a stored startid, this should be returned.
        $expected = get_config('tool_metadata', 'process_file_' . $extractor->get_name() . '_startid');
        $actual = $method->invoke($task, $extractor->get_name());
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test setting the startid for processing file extractions for specific extractor.
     */
    public function test_set_extractor_startid() {
        $task = new \tool_metadata\tests\mock_process_extractions_task();
        $extractor = new \metadataextractor_mock\extractor();
        unset_config('process_file_' . $extractor->get_name() . '_startid', 'tool_metadata');
        $value = 9999;

        // We're testing a private method, so we need to setup reflector magic.
        $method = new ReflectionMethod('\tool_metadata\tests\mock_process_extractions_task', 'set_extractor_startid');
        $method->setAccessible(true); // Allow accessing of private method.
        $method->invoke($task, $extractor->get_name(), $value);

        // Method should correctly set config 'process_file_{$extractor::name}_startid'.
        $actual = get_config('tool_metadata', 'process_file_' . $extractor->get_name() . '_startid');
        $this->assertEquals($value, $actual);

        $value = 'No integer values should throw a type error';
        $this->expectException(TypeError::class);
        $method->invoke($task, $extractor->get_name(), $value);
    }
}
