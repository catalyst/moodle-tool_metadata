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

    const EXTRACTION_STATUS_COMPLETE = 200;

    const EXTRACTION_STATUS_PENDING = 201;

    const EXTRACTION_STATUS_ACCEPTED = 202;

    const EXTRACTION_STATUS_NOT_FOUND = 404;

    const EXTRACTION_STATUS_ERROR = 500;

    /**
     * Create metadata in the {metadata} table and return a metadata object or false if metadata could not be created.
     *
     * @param \stored_file $file the file to create metadata for.
     *
     * @return \tool_metadata\metadata_model|false an instance of the metadata model or one of its' children.
     */
    public function create_file_metadata(stored_file $file);

    /**
     * Read metadata for a specific Moodle file.
     *
     * @param \stored_file $file
     *
     * @return \tool_metadata\metadata_model|false
     */
    public function read_file_metadata(stored_file $file);

    /**
     * Update the stored metadata for a Moodle file.
     *
     * @param \stored_file $file
     * @param $metadata \tool_metadata\metadata_model object containing updated metadata to store.
     *
     * @return \tool_metadata\metadata_model|false
     */
    public function update_file_metadata(stored_file $file, $metadata);

    /**
     * Delete the stored metadata for a Moodle file.
     *
     * @param \stored_file $file
     *
     * @return bool
     */
    public function delete_file_metadata(stored_file $file);

    /**
     * Get all file extensions supported by the implementing class.
     *
     * @return array listing all supported file extensions.
     */
    public function get_supported_file_extensions();

}