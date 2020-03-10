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
 * process_file_extractions_task_test.
 *
 * @package    tool_metadata
 * @copyright  2020 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_metadata\extraction;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/admin/tool/metadata/constants.php');
require_once($CFG->dirroot . '/admin/tool/metadata/tests/mock_metadataextractor_extractor.php');
require_once($CFG->dirroot . '/admin/tool/metadata/tests/mock_metadataextractor_extractor_two.php');

/**
 * process_file_extractions_task_test.
 *
 * @package    tool_metadata
 * @copyright  2020 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      tool_metadata
 */
class process_file_extractions_task_testcase extends advanced_testcase {

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
    }

    public function test_get_file_extractions_to_process() {

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

        $task = new \tool_metadata\task\process_file_extractions_task();
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
     * Test that when we get the extraction to process, we set the start id
     * for the next run correctly.
     */
    public function test_get_extractions_to_process_start_id_set_correctly() {
        global $DB;

        // Remove all files from the database, so we know exactly how many we have
        // for test.
        $DB->delete_records('files');

        $filecount = \tool_metadata\task\process_file_extractions_task::MAX_PROCESSES + 1;
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
            $files[] = $fs->create_file_from_string($filerecord, "This is test file #$i");
        }

        $extractor = new \metadataextractor_mock\extractor();
        $extractortwo = new \metadataextractor_mocktwo\extractor();

        $task = new \tool_metadata\task\process_file_extractions_task();
        unset_config('processfilestartid', 'tool_metadata');
        $actual = $task->get_extractions_to_process(['mock' => $extractor, 'mocktwo' => $extractortwo]);

        // Expect 2 times the max processes as we are using 2 extractors.
        $expected = 2 * \tool_metadata\task\process_file_extractions_task::MAX_PROCESSES;
        $this->assertCount($expected, $actual);

        $actual = $task->get_extractions_to_process(['mock' => $extractor, 'mocktwo' => $extractortwo]);
        // Expect 2 times the amount over max processes as we are using 2 extractors.
        $expected = 2 * ($filecount - \tool_metadata\task\process_file_extractions_task::MAX_PROCESSES);
        $this->assertCount($expected, $actual);
    }

    public function test_process_file_extractions() {

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

        $task = new \tool_metadata\task\process_file_extractions_task();
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


}