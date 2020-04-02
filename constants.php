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
 * Definition of constants for metadata API.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * TOOL_METADATA_RESOURCE_TYPE_FILE - Metadata for a stored_file.
 */
define('TOOL_METADATA_RESOURCE_TYPE_FILE', 'file');

/**
 * TOOL_METADATA_RESOURCE_TYPE_URL - Metadata for a mod_url instance.
 */
define('TOOL_METADATA_RESOURCE_TYPE_URL', 'url');

/**
 * TOOL_METADATA_MAX_PROCESSES_DEFAULT - Default number of maximum asynchronous extraction tasks to queue at once.
 */
define('TOOL_METADATA_MAX_PROCESSES_DEFAULT', 1000);

/**
 * TOOL_METADATA_FAIL_DELAY_DEFAULT - Default fail delay, over which, metadata extraction will no longer be attempted.
 */
define('TOOL_METADATA_FAIL_DELAY_THRESHOLD_DEFAULT', 86400);
