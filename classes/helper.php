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
 * Helper class for metadata API.
 *
 * The metadata helper class provides common functionality to the metadata API.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_metadata;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/admin/tool/metadata/constants.php');
require_once("$CFG->dirroot/mod/url/locallib.php");


/**
 * Helper class for metadata API.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /**
     * Get an array of all possible resource types.
     *
     * @return array
     */
    public static function get_metadata_resource_types() {
        return [
            TOOL_METADATA_RESOURCE_TYPE_FILE,
            TOOL_METADATA_RESOURCE_TYPE_URL
        ];
    }

    /**
     * Get the resource id of a resource.
     *
     * @param object $resource the resource object.
     * @param string $type the resource type.
     *
     * @return int
     * @throws \tool_metadata\extraction_exception
     */
    public static function get_resource_id($resource, string $type) {
        switch ($type) {
            case TOOL_METADATA_RESOURCE_TYPE_FILE :
                $result = $resource->get_id();
                break;
            case TOOL_METADATA_RESOURCE_TYPE_URL :
                $result = $resource->id;
                break;
            default :
                throw new extraction_exception('error:unsupportedresourcetype');
                break;
        }

        return $result;
    }

    /**
     * Get the resourcehash for a resource.
     *
     * NOTE: This should NOT be relied on for checking if metadata for a resource has been
     * extracted, as some resources (ie. urls) have dynamic content (ie. dynamically generated
     * unique ids in tags) and will never return the same resourcehash. This just provides a
     * point in time hash, which is used as a key linking an extraction to extracted metadata.
     *
     * @param object $resource the resource object.
     * @param string $type the resource type.
     *
     * @return string|false the resourcehash or false if couldn't get the contenthash.
     * @throws \tool_metadata\extraction_exception
     */
    public static function get_resourcehash($resource, string $type): string {

        switch ($type) {
            case TOOL_METADATA_RESOURCE_TYPE_FILE :
                $result = $resource->get_contenthash();
                break;
            case TOOL_METADATA_RESOURCE_TYPE_URL :
                $cm = get_coursemodule_from_instance('url', $resource->id, $resource->course, false, MUST_EXIST);
                $fullurl = url_get_full_url($resource, $cm, $resource->course);
                $result = sha1($fullurl);
                break;
            default :
                throw new extraction_exception('error:unsupportedresourcetype');
                break;
        }

        return $result;
    }

    /**
     * Get the last time a resource was modified.
     *
     * @param object $resource the resource object.
     * @param string $type the resource type.
     *
     * @return int unix timestamp of last modification.
     * @throws \tool_metadata\extraction_exception
     */
    public function get_resource_timemodified($resource, $type) {
        switch ($type) {
            case TOOL_METADATA_RESOURCE_TYPE_FILE :
                $result = $resource->get_timemodified();
                break;
            case TOOL_METADATA_RESOURCE_TYPE_URL :
                $result = $resource->timemodified;
                break;
            default :
                throw new extraction_exception('error:unsupportedresourcetype');
                break;
        }

        return $result;
    }

    /**
     * Get the table name of a resource's records.
     *
     * @param string $type the resource type.
     *
     * @return string the table name containing resource records for this resource type.
     * @throws \tool_metadata\extraction_exception
     */
    public static function get_resource_table($type) : string {
        switch ($type) {
            case TOOL_METADATA_RESOURCE_TYPE_FILE :
                $tablename = 'files';
                break;
            case TOOL_METADATA_RESOURCE_TYPE_URL :
                $tablename = 'url';
                break;
            default :
                throw new extraction_exception('error:unsupportedresourcetype');
                break;
        }

        return $tablename;
    }

    /**
     * Get a resource instance by resource id and type of resource.
     *
     * @param int $resourceid the unique id of the resource.
     * @param string $type the resource type.
     *
     * @return mixed
     * @throws \tool_metadata\extraction_exception
     */
    public static function get_resource(int $resourceid, string $type) {
        global $DB;

        switch ($type) {
            case TOOL_METADATA_RESOURCE_TYPE_FILE :
                $fs = get_file_storage();
                $resource = $fs->get_file_by_id($resourceid);
                break;
            case TOOL_METADATA_RESOURCE_TYPE_URL :
                $resource = $DB->get_record('url', ['id' => $resourceid]);
                break;
            default :
                throw new extraction_exception('error:unsupportedresourcetype');
                break;
        }

        return $resource;
    }

    /**
     * Get all database field names for a resource type.
     *
     * @param string $type the resource type to get fields for.
     *
     * @return array
     * @throws \tool_metadata\extraction_exception
     */
    public static function get_resource_fields(string $type) {
        global $DB;

        $fields = [];

        $columns = $DB->get_columns(self::get_resource_table($type));

        foreach ($columns as $column) {
            $fields[] = $column->__get('name');
        }

        return $fields;
    }

    /**
     * Get any extraction filters which have been applied in settings.
     *
     * @param string $type the resource type to get filters for.
     *
     * @return array $result array of filter objects in the shape of:
     *      {'type'=> resourcetype, 'field'=> DB field to filter by, 'value'=> field value to exclude records by}.
     * @throws \tool_metadata\extraction_exception if extraction filters setting is invalid.
     */
    public static function get_resource_extraction_filters(string $type) {

        $result = [];
        $jsonfilters = get_config('tool_metadata', 'extraction_filters');

        if (!empty($jsonfilters)) {
            $filters = json_decode($jsonfilters);
            $fields = self::get_resource_fields($type);

            if (is_null($filters)) {
                throw new extraction_exception('error:invalidextractionfilters');
            }

            foreach ($filters as $filter) {
                if ($filter->type == $type && in_array($filter->field, $fields)) {
                    $result[] = $filter;
                }
            }
        }
        return $result;
    }
}