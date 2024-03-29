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
 * Metadata extraction task test.
 *
 * @package    tool_metadata
 * @copyright  2020 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_metadata\helper;
use tool_metadata\mock_file_builder;
use tool_metadata\task\metadata_extraction_task;

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
class metadata_extraction_task_test extends advanced_testcase {

    public function setUp(): void {
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

        // Emulate the installation of the mock metadataextractor plugin so it can be found.
        $plugin = new stdClass();
        $plugin->plugin = 'metadataextractor_mock';
        $plugin->name = 'version';
        $plugin->value = time();

        $DB->insert_record('config_plugins', $plugin);

    }

    public function tearDown(): void {
        global $DB;

        $dbman = $DB->get_manager();
        $table = new \xmldb_table(\metadataextractor_mock\metadata::TABLE);
        $dbman->drop_table($table);
    }

    public function test_execute_success() {
        [$metadata, $resource] = mock_file_builder::mock_pdf();
        $extractor = new metadataextractor_mock\extractor();
        $type = TOOL_METADATA_RESOURCE_TYPE_FILE;

        $task = new metadata_extraction_task();
        $task->set_custom_data(['resourceid' => helper::get_resource_id($resource, $type), 'type' => $type,
            'plugin' => $extractor->get_name()]);

        // We are expecting mtrace to output tool_metadata:... messages during task execution.
        $this->expectOutputRegex("/tool_metadata\:/");
        $task->execute();

        // Successful extraction should return a complete status.
        $extraction = tool_metadata\api::get_extraction($resource, $type, $extractor);
        $this->assertEquals(\tool_metadata\extraction::STATUS_COMPLETE, $extraction->get('status'));

        $course = $this->getDataGenerator()->create_course();
        $resource = $this->getDataGenerator()->create_module('url', ['course' => $course]);
        $type = TOOL_METADATA_RESOURCE_TYPE_URL;

        $task = new metadata_extraction_task();
        $task->set_custom_data(['resourceid' => helper::get_resource_id($resource, $type), 'type' => $type,
            'plugin' => $extractor->get_name()]);

        $task->execute();

        // Unsupported extraction should return a not-supported status.
        $extraction = tool_metadata\api::get_extraction($resource, $type, $extractor);
        $this->assertEquals(\tool_metadata\extraction::STATUS_NOT_SUPPORTED, $extraction->get('status'));
    }

    public function test_execute_failed() {
        [$metadata, $resource] = mock_file_builder::mock_pdf();
        // We use 'triggerfail' license in the mock class as a hack to trigger throwing an exception.
        $resource->set_license('triggerfail');

        $extractor = new metadataextractor_mock\extractor();
        $type = TOOL_METADATA_RESOURCE_TYPE_FILE;

        $task = new metadata_extraction_task();
        // Set the fail delay to the threshold, to test that it task passes correctly.
        $task->set_fail_delay(TOOL_METADATA_FAIL_DELAY_THRESHOLD_DEFAULT);
        $task->set_custom_data(['resourceid' => helper::get_resource_id($resource, $type), 'type' => $type,
            'plugin' => $extractor->get_name()]);

        // We are expecting mtrace to output tool_metadata:... messages during task execution.
        $this->expectOutputRegex("/tool_metadata\:/");
        $task->execute();

        // Failed extraction task with fail delay exceeding threshold should return an error status.
        $extraction = tool_metadata\api::get_extraction($resource, $type, $extractor);
        $this->assertEquals(\tool_metadata\extraction::STATUS_NOT_SUPPORTED, $extraction->get('status'));

        $task = new metadata_extraction_task();
        // Set the fail delay below the threshold, to test that it throws as exception, triggering task retry.
        $task->set_fail_delay(0);
        $task->set_custom_data(['resourceid' => helper::get_resource_id($resource, $type), 'type' => $type,
            'plugin' => $extractor->get_name()]);

        // We are expecting an exception, triggering a task retry in task manager.
        $this->expectException(\tool_metadata\extraction_exception::class);
        $task->execute();
    }
}
