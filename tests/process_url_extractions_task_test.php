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
 * process_url_extractions_task_test.
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
 * process_url_extractions_task_test.
 *
 * @package    tool_metadata
 * @copyright  2020 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      tool_metadata
 */
class process_url_extractions_task_testcase extends advanced_testcase {

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

    public function test_get_url_extractions_to_process() {

        // Create a test URL.
        $course = $this->getDataGenerator()->create_course();
        $url = $this->getDataGenerator()->create_module('url', ['course' => $course]);

        $extractor = new \metadataextractor_mock\extractor();
        $extractortwo = new \metadataextractor_mocktwo\extractor();

        $task = new \tool_metadata\task\process_url_extractions_task();
        $actual = $task->get_extractions_to_process(['mock' => $extractor, 'mocktwo' => $extractortwo]);

        // URL extractions should be created for all extractors, regardless of if an extraction record exists.
        $this->assertArrayHasKey($url->id . $extractor->get_name(), $actual);
        $this->assertArrayHasKey($url->id . $extractortwo->get_name(), $actual);
        $urlextraction = $actual[$url->id . $extractor->get_name()];
        $urlextractiontwo = $actual[$url->id . $extractortwo->get_name()];

        // URL extraction resourceid should match the url table record.
        $this->assertEquals($url->id, $urlextraction->resourceid);
        $this->assertEquals($url->id, $urlextractiontwo->resourceid);

        // All url extraction records should identify which extractor was being used.
        $this->assertEquals($extractor->get_name(), $urlextraction->extractor);
        $this->assertEquals($extractortwo->get_name(), $urlextractiontwo->extractor);

        // The query result should have no extraction details if no existing extraction record.
        $this->assertNull($urlextraction->extractionid);
        $this->assertNull($urlextraction->status);
        $this->assertNull($urlextractiontwo->extractionid);
        $this->assertNull($urlextractiontwo->status);

        // Add extraction record for the URL for one extractor only.
        $extraction = new \tool_metadata\extraction($url, TOOL_METADATA_RESOURCE_TYPE_URL, $extractor);
        $extraction->save();

        $actual = $task->get_extractions_to_process(['mock' => $extractor, 'mocktwo' => $extractortwo]);
        $urlextraction = $actual[$url->id . $extractor->get_name()];
        $urlextractiontwo = $actual[$url->id . $extractortwo->get_name()];

        // URLs with an extraction record should populate the extractor details.
        $this->assertEquals($extraction->get('id'), $urlextraction->extractionid);
        $this->assertEquals($extraction->get('status'), $urlextraction->status);
        $this->assertNull($urlextractiontwo->extractionid);
        $this->assertNull($urlextractiontwo->status);
    }

    /**
     * Test that when we get the extraction to process, we set the start id
     * for the next run correctly.
     */
    public function test_get_extractions_to_process_start_id_set_correctly() {
        global $DB;

        // Remove all urls from the database, so we know exactly how many we have
        // for test.
        $DB->delete_records('url');

        $urlcount = \tool_metadata\task\process_file_extractions_task::MAX_PROCESSES + 1;
        $urls = [];

        $course = $this->getDataGenerator()->create_course();
        // Create more urls than max processes starting at 1, (as we can't index a file by 0 (zero)).
        for ($i = 1; $i <= $urlcount; $i++) {
            $urls[] = $this->getDataGenerator()->create_module('url', ['course' => $course]);
        }

        $extractor = new \metadataextractor_mock\extractor();
        $extractortwo = new \metadataextractor_mocktwo\extractor();

        $task = new \tool_metadata\task\process_url_extractions_task();
        unset_config('processurlstartid', 'tool_metadata');
        $actual = $task->get_extractions_to_process(['mock' => $extractor, 'mocktwo' => $extractortwo]);

        // Expect 2 times the max processes as we are using 2 extractors.
        $expected = 2 * \tool_metadata\task\process_file_extractions_task::MAX_PROCESSES;
        $this->assertCount($expected, $actual);

        $actual = $task->get_extractions_to_process(['mock' => $extractor, 'mocktwo' => $extractortwo]);
        // Expect 2 times the amount over max processes as we are using 2 extractors.
        $expected = 2 * ($urlcount - \tool_metadata\task\process_file_extractions_task::MAX_PROCESSES);
        $this->assertCount($expected, $actual);
    }

    public function test_process_url_extractions() {

        // Create a test URL.
        $course = $this->getDataGenerator()->create_course();
        $url = $this->getDataGenerator()->create_module('url', ['course' => $course]);

        $extractor = new \metadataextractor_mock\extractor();
        $extractortwo = new \metadataextractor_mocktwo\extractor();

        $task = new \tool_metadata\task\process_url_extractions_task();
        $urlextractions = $task->get_extractions_to_process(['mock' => $extractor, 'mocktwo' => $extractortwo]);
        // Limit records to the test url created, to avoid processing standard moodle URLs in test.
        $records = [
            $urlextractions[$url->id . $extractor->get_name()],
            $urlextractions[$url->id . $extractortwo->get_name()]
        ];

        $status = $task->process_extractions($records, ['mock' => $extractor, 'mocktwo' => $extractortwo]);

        $this->assertEquals(0, $status->completed);
        $this->assertEquals(2, $status->queued);
        $this->assertEquals(0, $status->pending);
        $this->assertEquals(0, $status->errors);
        $this->assertEquals(0, $status->unsupported);
        $this->assertEquals(0, $status->unknown);

        // Get the updated extraction records.
        $urlextractions = $task->get_extractions_to_process(['mock' => $extractor, 'mocktwo' => $extractortwo]);
        // Limit records to the test URL created, to avoid processing standard moodle URLs in test.
        $records = [
            $urlextractions[$url->id . $extractor->get_name()],
            $urlextractions[$url->id . $extractortwo->get_name()]
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