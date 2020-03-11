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
 * The scheduled task for extraction of metadata for urls.
 *
 * @package    tool_metadata
 * @copyright  2020 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_metadata\task;

defined('MOODLE_INTERNAL') || die();

/**
 * The scheduled task for extraction of metadata for urls.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_url_extractions_task extends process_extractions_base_task {

    /**
     * The resourcetype extraction task supports.
     */
    const RESOURCE_TYPE = TOOL_METADATA_RESOURCE_TYPE_URL;

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     *
     */
    public function get_name() : string {
        return get_string('task:processurls', 'tool_metadata');
    }

    /**
     * Get extraction condition for URL resources preventing extraction of non-http(s) URLs.
     *
     * @param string $tablealias the table alias being used for the resource table.
     *
     * @return array $conditions object[] of object instances containing:
     *  {
     *      'sql' => (string) The SQL statement to add to where clause.
     *      'params => (array) Values for bound parameters in the SQL statement indexed by parameter name.
     *  }
     */
    public function get_resource_extraction_conditions($tablealias) {
        global $DB;

        $conditions = [];

        // URL metadata can only be extracted for http(s) scheme URLs, no FTP support.
        $ishttp = new \stdClass();
        $ishttp->sql = $DB->sql_like($tablealias . '.externalurl', ':httplike', false, false);
        $ishttp->params = ['httplike' => 'http%'];
        $conditions[] = $ishttp;

        return $conditions;
    }
}
