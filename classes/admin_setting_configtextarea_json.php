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
 * The admin setting for managing component 'tool_metadata' subplugins.
 *
 * @package    tool_metadata
 * @copyright  2020 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_metadata;

use admin_setting_configtextarea;

defined('MOODLE_INTERNAL') || die();

/**
 * General text area without html editor for setting valid JSON strings only.
 *
 * @package    tool_metadata
 * @copyright  2020 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_configtextarea_json extends admin_setting_configtextarea {

    /**
     * Validate data before storage
     *
     * @param string $data the text area data.
     *
     * @return mixed true if ok string if error found
     */
    public function validate($data) {

        if (empty($data)) {
            $result = true;
        } else {
            $decoded = json_decode($data);

            if (is_null($decoded)) {
                $result = get_string('settings:error:invalidjson', 'tool_metadata');
            } else {
                $result = true;
            }
        }

        return $result;
    }
}