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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/admin/tool/metadata/constants.php');

/**
 * process_url_extractions_task_test.
 *
 * @package    tool_metadata
 * @copyright  2020 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      tool_metadata
 */
class process_url_extractions_task_testcase extends advanced_testcase {

    /**
     * @var string[] mock metadataextractor plugins.
     */
    protected $mockplugins;

    public function setUp() {
        global $DB;

        $this->resetAfterTest();

        // Create tables for mock metadataextractor subplugins.
        $dbman = $DB->get_manager();
        $this->mockplugins = ['mock', 'mocktwo'];
        foreach ($this->mockplugins as $plugin) {
            $table = new \xmldb_table('metadataextractor_' . $plugin);
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

            // Insert version records for the mock metadataextractor plugin, otherwise it will not be seen as installed.
            $record = new stdClass();
            $record->plugin = 'metadataextractor_' . $plugin;
            $record->name = 'version';
            $record->value = time();

            $DB->insert_record('config_plugins', $record);
        }
        // Enable the mock plugins.
        \tool_metadata\plugininfo\metadataextractor::set_enabled_plugins($this->mockplugins);
    }

    public function test_get_resource_extraction_conditions() {
        global $DB;

        // Drop the mock metadataextractor tables to avoid any funny business.
        $dbman = $DB->get_manager();
        foreach ($this->mockplugins as $plugin) {
            $table = new \xmldb_table('metadataextractor_' . $plugin);
            $dbman->drop_table($table);
        }
    }

    public function test_get_url_extractions_to_process() {
        global $DB;

        // Create a test URL.
        $course = $this->getDataGenerator()->create_course();
        $httpurl = $this->getDataGenerator()->create_module('url', ['course' => $course, 'externalurl' => 'https://somesite.org']);
        $ftpurl = $this->getDataGenerator()->create_module('url', ['course' => $course, 'externalurl' => 'ftp://somesite.org']);

        $task = new \tool_metadata\task\process_url_extractions_task();

        $conditions = $task->get_resource_extraction_conditions();

        $select = '';
        $params = [];

        foreach ($conditions as $index => $condition) {
            if ($index != 0) {
                $select .= ' AND ';
            }
            $select .= $condition->sql;
            $params = array_merge($params, $condition->params);
        }

        // SQL and params should exclude non-HTTP(S) URLs from query results.
        $records = $DB->get_records_select('url', $select, $params);
        $this->assertArrayHasKey($httpurl->id, $records);
        $this->assertArrayNotHasKey($ftpurl->id, $records);
    }
}
