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
 * Exception thrown when extraction could not be completed.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_metadata;

defined('MOODLE_INTERNAL') || die();

/**
 * Exception thrown when metadata extraction could not be completed.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class extraction_exception extends \moodle_exception {

    /**
     * extraction_exception constructor.
     *
     * @param string $errorcode The name of the error string
     * @param string $module name of module containing error string
     * @param string $link The url where the user will be prompted to continue. If no url is provided the user will be directed
     * to the site index page.
     * @param mixed $a Extra words and phrases that might be required in the error string
     * @param string $debuginfo optional debugging information
     */
    public function __construct($errorcode, $module='', $link='', $a=null, $debuginfo=null) {
        parent::__construct($errorcode, $module, $link, $a, $debuginfo);
    }
}