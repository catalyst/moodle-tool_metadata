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
 * Mock extraction processing task for testing base class methods.
 *
 * @package    tool_metadata
 * @copyright  2020 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_metadata\tests;

use tool_metadata\task\process_extractions_base_task;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/admin/tool/metadata/constants.php');

/**
 * Mock extraction processing task for testing base class methods.
 *
 * @package    tool_metadata
 * @copyright  2020 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mock_process_extractions_task extends process_extractions_base_task {

    /**
     * The string resourcetype extraction task supports.
     */
    const RESOURCE_TYPE = TOOL_METADATA_RESOURCE_TYPE_FILE;

    /**
     * Should this task process resources cyclically?
     * (ie. once all resources are processed, should processing start again from the beginning
     * and reprocess all resources?)
     */
    const IS_CYCLICAL = false;

    /**
     * Mock task name.
     *
     * @return string the mock name.
     */
    public function get_name() {
        return 'Mock file extraction processing task';
    }

    /**
     * Get extraction condition for file resources preventing extraction of directories.
     *
     * @param string $tablealias the table alias being used for the resource table.
     *
     * @return array $conditions object[] of object instances containing:
     *  {
     *      'sql' => (string) The SQL statement to add to where clause.
     *      'params => (array) Values for bound parameters in the SQL statement indexed by parameter name.
     *  }
     */
    public function get_resource_extraction_conditions($tablealias = '') {
        global $DB;

        $conditions = [];

        $fieldname = empty($tablealias) ? 'filename' : "$tablealias.filename";

        // Do not extract metadata from file directories.
        $notdirectory = new \stdClass();
        $notdirectory->sql = $DB->sql_like($fieldname, ':directory', true, false, true);
        $notdirectory->params = ['directory' => '.'];
        $conditions[] = $notdirectory;

        return $conditions;
    }
}