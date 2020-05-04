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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/admin/tool/metadata/constants.php');

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
        $this->resetAfterTest();
    }

    /**
     * Test that the custom metadata extraction conditions for files work as intended.
     */
    public function test_get_resource_extraction_conditions() {
        global $DB;

        // Delete all files, so we know exactly what is in the file table.
        $DB->delete_records('files');

        $fs = get_file_storage();
        $syscontext = context_system::instance();

        // Create a test directory.
        $directory = $fs->create_directory($syscontext->id, 'tool_metadata', 'unittest', 0, '/');

        // Create a test document file.
        $filerecord = array(
            'contextid' => $syscontext->id,
            'component' => 'tool_metadata',
            'filearea'  => 'unittest',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => 'test.doc',
        );
        $file = $fs->create_file_from_string($filerecord, 'Test file');

        $task = new \tool_metadata\task\process_file_extractions_task();
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

        // SQL and params should exclude directory files from query results.
        $records = $DB->get_records_select('files', $select, $params);
        $this->assertArrayHasKey($file->get_id(), $records);
        $this->assertArrayNotHasKey($directory->get_id(), $records);
    }
}
