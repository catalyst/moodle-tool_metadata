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
 * Abstract class to be extended in metadata extractor subplugins.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_metadata;

use stored_file;

defined('MOODLE_INTERNAL') || die();

abstract class extractor implements extractor_strategy {

    /**
     * Required: The pluginname of the metadataextractor subplugin.
     */
    const METADATAEXTRACTOR_NAME = null;

    /**
     * Required: The table name of the metadataextractor subplugin metadata storage table.
     */
    const METADATA_TABLE = null;

    /**
     * Get the id of the metadata record for a resource.
     * (Return 0 if no record could be found)
     *
     * @param string $contenthash of the file to get id for.
     *
     * @return int $id of the metadata record
     */
    public function get_metadata_id(string $contenthash) {
        global $DB;

        $id = 0;

        $result = $DB->get_record(static::METADATA_TABLE, [
            'contenthash' => $contenthash
        ], 'id');

        if ($result) {
            $id = $result->id;
        }

        return $id;
    }

    /**
     * Get the name of this metadataextractor.
     *
     * @return string
     */
    public function get_name() {
        return static::METADATAEXTRACTOR_NAME;
    }

    /**
     * Attempt to create file metadata and store in database.
     *
     * @param \stored_file $file
     * @throws \tool_metadata\extraction_exception
     *
     * @return bool
     */
    public function create_file_metadata(stored_file $file) {
        // TODO: This MUST be overridden in extending metadataextractor subplugin extractor class.
        return false;
    }

    /**
     * Update the stored metadata for a Moodle file.
     *
     * @param \stored_file $file
     * @param  \tool_metadata\metadata $metadata object containing updated metadata to store.
     *
     * @return \tool_metadata\metadata|false
     */
    public function update_file_metadata(stored_file $file, metadata $metadata) {
        // TODO: This MUST be overridden in extending metadataextractor subplugin extractor class.
        return false;
    }

    /**
     * Delete the stored metadata for a resource.
     *
     * @param string $contenthash
     *
     * @return bool $deleted true if successfully deleted.
     */
    public function delete_metadata(string $contenthash) {
        global $DB;

        $deleted = $DB->delete_records(static::METADATA_TABLE, ['contenthash' => $contenthash]);

        return $deleted;
    }

    /**
     * Return a metadata model for a resource.
     *
     * @param string $contenthash
     *
     * @return false|\tool_metadata\metadata
     */
    public function read_metadata(string $contenthash) {
        global $DB;

        $metadata = false;

        $record = $DB->get_record(static::METADATA_TABLE, ['contenthash' => $contenthash]);

        if ($record) {
            $metadataclass = '\\' . static::METADATAEXTRACTOR_NAME . '\\metadata';
            $metadata = new $metadataclass($record);
        }

        return $metadata;
    }
}