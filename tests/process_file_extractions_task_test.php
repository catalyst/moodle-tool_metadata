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
        $table = new \xmldb_table(\metadataextractor_mock\extractor::METADATA_TABLE);
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

        // Create a table for mocktwo metadataextractor subplugin.
        $dbman = $DB->get_manager();
        $table = new \xmldb_table(\metadataextractor_mocktwo\extractor::METADATA_TABLE);
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
        $actual = $task->get_file_extractions_to_process(['mock' => $extractor, 'mocktwo' => $extractortwo]);

        // File extractions should be created for all extractors, regardless of if an extraction record exists.
        $this->assertArrayHasKey($file->get_id() . $extractor->get_name(), $actual);
        $this->assertArrayHasKey($file->get_id() . $extractortwo->get_name(), $actual);
        $fileextraction = $actual[$file->get_id() . $extractor->get_name()];
        $fileextractiontwo = $actual[$file->get_id() . $extractortwo->get_name()];

        // File id should match the file table record.
        $this->assertEquals($file->get_id(), $fileextraction->fileid);
        $this->assertEquals($file->get_id(), $fileextractiontwo->fileid);

        // The query result should have no extraction details if no existing extraction record.
        $this->assertNull($fileextraction->extractionid);
        $this->assertNull($fileextraction->extractor);
        $this->assertNull($fileextraction->status);
        $this->assertNull($fileextractiontwo->extractionid);
        $this->assertNull($fileextractiontwo->extractor);
        $this->assertNull($fileextractiontwo->status);

        // Add extraction record for the file for one extractor only.
        $extraction = new \tool_metadata\extraction($file, TOOL_METADATA_RESOURCE_TYPE_FILE, $extractor);
        $extraction->save();

        $actual = $task->get_file_extractions_to_process(['mock' => $extractor, 'mocktwo' => $extractortwo]);
        $fileextraction = $actual[$file->get_id() . $extractor->get_name()];
        $fileextractiontwo = $actual[$file->get_id() . $extractortwo->get_name()];

        // Files with an extraction record should populate the extractor details.
        $this->assertEquals($fileextraction->extractor, $extractor->get_name());
        $this->assertEquals($extraction->get('id'), $fileextraction->extractionid);
        $this->assertEquals($extraction->get('extractor'), $fileextraction->extractor);
        $this->assertEquals($extraction->get('status'), $fileextraction->status);
        $this->assertNull($fileextractiontwo->extractionid);
        $this->assertNull($fileextractiontwo->extractor);
        $this->assertNull($fileextractiontwo->status);
    }

    public function test_process_file_extractions() {
        global $DB;

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
        $fileextractions = $task->get_file_extractions_to_process(['mock' => $extractor, 'mocktwo' => $extractortwo]);
        // Limit records to the test file created, to avoid processing standard moodle files in test.
        $records = [
            $fileextractions[$file->get_id() . $extractor->get_name()],
            $fileextractions[$file->get_id() . $extractortwo->get_name()]
        ];

        $status = $task->process_file_extractions($records, ['mock' => $extractor, 'mocktwo' => $extractortwo]);

        $this->assertEquals(0, $status->completed);
        $this->assertEquals(2, $status->queued);
        $this->assertEquals(0, $status->pending);
        $this->assertEquals(0, $status->errors);
        $this->assertEquals(0, $status->unsupported);
        $this->assertEquals(0, $status->unknown);

        // Get the updated extraction records.
        $fileextractions = $task->get_file_extractions_to_process(['mock' => $extractor, 'mocktwo' => $extractortwo]);
        // Limit records to the test file created, to avoid processing standard moodle files in test.
        $records = [
            $fileextractions[$file->get_id() . $extractor->get_name()],
            $fileextractions[$file->get_id() . $extractortwo->get_name()]
        ];
        $status = $task->process_file_extractions($records, ['mock' => $extractor, 'mocktwo' => $extractortwo]);

        $this->assertEquals(0, $status->completed);
        $this->assertEquals(0, $status->queued);
        $this->assertEquals(2, $status->pending);
        $this->assertEquals(0, $status->errors);
        $this->assertEquals(0, $status->unsupported);
        $this->assertEquals(0, $status->unknown);

        // Change the status of one extraction to an error.
        $records[0]->status = extraction::STATUS_ERROR;

        $status = $task->process_file_extractions($records, ['mock' => $extractor, 'mocktwo' => $extractortwo]);
        $this->assertEquals(0, $status->completed);
        $this->assertEquals(0, $status->queued);
        $this->assertEquals(1, $status->pending);
        $this->assertEquals(1, $status->errors);
        $this->assertEquals(0, $status->unsupported);
        $this->assertEquals(0, $status->unknown);
    }
}