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
 * Interface describing the strategy for extracting metadata from a Moodle resource.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_metadata;

use stored_file;

defined('MOODLE_INTERNAL') || die();

/**
 * Interface describing the strategy for extracting metadata from a Moodle resource.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface extractor_strategy {

    /**
     * Attempt to create file metadata and store in database.
     *
     * @param \stored_file $file
     * @throws \tool_metadata\extraction_exception
     *
     * @return bool true if metadata successfully created, false otherwise.
     */
    public function create_file_metadata(stored_file $file);

    /**
     * Update the stored metadata for a Moodle file.
     *
     * @param \stored_file $file
     * @param $metadata \tool_metadata\metadata object containing updated metadata to store.
     *
     * @return \tool_metadata\metadata|false
     */
    public function update_file_metadata(stored_file $file, metadata $metadata);

    /**
     * Delete the stored metadata for a resource.
     *
     * @param string $contenthash
     *
     * @return bool
     */
    public function delete_metadata(string $contenthash);

    /**
     * Read stored metadata for a resource.
     *
     * @param string $contenthash the contenthash of the resource.
     *
     * @return \tool_metadata\metadata|false
     */
    public function read_metadata(string $contenthash);

}