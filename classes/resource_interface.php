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
 * An interface for metadata resources to extract metadata from.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_metadata;

defined('MOODLE_INTERNAL') || die();

/**
 * The interface for metadata resources to extract metadata from.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface resource_interface {

    /**
     * resource_interface constructor.
     *
     * @param int $id the id of the record.
     * @param string $type the resource module name.
     */
    public function __construct(int $id, string $type);

    /**
     * Get the resource id.
     *
     * @return int the id of the record this resource represents.
     */
    public function get_id() : int;

    /**
     * Get the resource type.
     *
     * @return string the module name of the resource.
     */
    public function get_type() : string;

    /**
     * Get a unique hash of resource.
     *
     * @return string sha1 hash representing the resource.
     */
    public function get_hash() : string;

    /**
     * Can metadata be extracted from this resource instance?
     *
     * @return bool
     */
    public function can_extract_metadata() : bool;

}

