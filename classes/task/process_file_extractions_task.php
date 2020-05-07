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
 * The scheduled task for extraction of metadata for files.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_metadata\task;

defined('MOODLE_INTERNAL') || die();

/**
 * The scheduled task for extraction of metadata for files.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_file_extractions_task extends process_extractions_base_task {

    /**
     * The resourcetype extraction task supports.
     */
    const RESOURCE_TYPE = TOOL_METADATA_RESOURCE_TYPE_FILE;

    /**
     * Only ever process a file resource once.
     *
     * The content of file resources should never be changed, as when a file is edited in Moodle
     * a new record is created, therefore it makes no sense to reprocess file resources.
     */
    const IS_CYCLICAL = false;

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     *
     */
    public function get_name() : string {
        return get_string('task:processfiles', 'tool_metadata');
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
