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
 * upgrade/install related functions.
 *
 * @package    tool_metadata
 * @copyright  2020 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

/**
 * Remove all extraction records without a resource id.
 *
 * @param $oldversion int version number of tool_metadata prior to upgrade.
 */
function remove_extractions_without_resourceid(int $oldversion) {
    global $DB;

    // Table name was changed, check version number to delete from correct table.
    if ($oldversion < 2020040103) {
        $DB->delete_records('metadata_extractions', ['resourceid' => 0]);
    } else {
        $DB->delete_records('tool_metadata_extractions', ['resourceid' => 0]);
    }
}
