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
 * Extractor base class to be extended in metadataextractor subplugins.
 *
 * @package    tool_metadata
 * @copyright  2020 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_metadata;

defined('MOODLE_INTERNAL') || die();

/**
 * Extractor base class to be extended in metadataextractor subplugins.
 *
 * @package    tool_metadata
 * @copyright  2020 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class extractor {

    /**
     * Required: The pluginname of the metadataextractor subplugin.
     */
    const METADATAEXTRACTOR_NAME = null;

    /**
     * Required: The table name of the metadataextractor subplugin metadata storage table.
     */
    const METADATA_TABLE = null;

    /**
     * Get the name of metadataextractor plugin this extractor is for.
     *
     * @return string
     */
    public function get_name() : string {
        return static::METADATAEXTRACTOR_NAME;
    }

    /**
     * Get the table name where this extractor stores metadata.
     *
     * @return string
     */
    public function get_table() : string {
        return static::METADATA_TABLE;
    }

    /**
     * Get an array of all resourcehashes this extractor has metadata for.
     *
     * @return array
     */
    public function get_extracted_resourcehashes() : array {
        global $DB;

        $records = $DB->get_records(static::METADATA_TABLE, null, null, 'resourcehash');
        $result = array_keys($records);

        return $result;
    }

    /**
     * Extract metadata for a resource.
     *
     * @param object $resource an instance of a resource to extract metadata for.
     * @param string $type type of the moodle resource.
     *
     * @return \tool_metadata\metadata|false instance of extracted metadata or false if extraction failed.
     * @throws \tool_metadata\extraction_exception
     */
    public function extract_metadata($resource, string $type) {

        $method = 'extract_' . $type . '_metadata';
        if (method_exists(static::class, $method)) {
            $result = static::$method($resource);
        } else {
            throw new extraction_exception('error:unsupportedresourcetype',
                'tool_metadata', '', ['name' => static::get_name(), 'type' => $type]);
        }

        return $result;
    }

    /**
     * Read metadata for a resource.
     *
     * @param string $resourcehash the unique hash of resource content or resource content id.
     *
     * @return \tool_metadata\metadata|null metadata instance or null if no metadata found.
     * @throws \tool_metadata\extraction_exception
     */
    public function get_metadata(string $resourcehash) {
        global $DB;

        $record = $DB->get_record(static::METADATA_TABLE,
            ['resourcehash' => $resourcehash]);

        if (!empty($record)) {
            $metadataclass = '\\metadataextractor_' . static::get_name() . '\\metadata';
            if (class_exists($metadataclass)) {
                $metadata = new $metadataclass($resourcehash, $record);
            } else {
                throw new extraction_exception('error:metadataclassnotfound', 'tool_metadata', '', static::get_name());
            }
        } else {
            $metadata = null;
        }

        return $metadata;
    }

    /**
     * Does this extractor have metadata for a resourcehash?
     *
     * @param string $resourcehash the unique hash of resource content (or resource content identifier).
     *
     * @return bool
     */
    public function has_metadata(string $resourcehash) {
        global $DB;

        $count = $DB->count_records(static::METADATA_TABLE, ['resourcehash' => $resourcehash]);

        $result = ($count > 0);

        return $result;
    }

    /**
     * Get an array of supported resource types extractor instance can
     * extract metadata for.
     *
     * @return array of string resource types.
     */
    public function get_supported_resource_types() {
        $types = [];
        // Use late static binding to get methods for extending class.
        foreach (get_class_methods(static::class) as $method) {
            if (preg_match('/^extract_[a-z]*_metadata$/i', $method)) {
                $methodparts = explode('_', $method);
                $types[] = $methodparts[1];
            }
        }
        return $types;
    }

    /**
     * Does this extractor support a particular resource type?
     *
     * @param string $type the type to check is supported.
     *
     * @return bool true if resource type supported, false otherwise.
     */
    public function supports_resource_type(string $type) {
        $result = false;

        if (in_array($type, static::get_supported_resource_types())) {
            $result = true;
        }

        return $result;
    }

    /**
     * Custom validation for resources.
     *
     * Override this method in extending classes to add custom validation for resources.
     *
     * @param object $resource the resource instance to check
     * @param string $type the type of resource.
     *
     * @return bool
     */
    public function validate_resource($resource, string $type) : bool {
        return true;
    }
}
