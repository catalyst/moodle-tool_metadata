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
 * The main api for handling metadata.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_metadata;

use stored_file;
use tool_metadata\plugininfo\metadataextractor;
use tool_metadata\task\metadata_extraction_task;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/admin/tool/metadata/constants.php');

/**
 * The main api for handling metadata.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {

    /**
     * API supported resource types.
     */
    const TOOL_METADATA_RESOURCE_TYPES = ['file', 'url'];

    /**
     * Get array of supported resource types.
     *
     * @return array string[]
     */
    public static function get_supported_resource_types() {
        return self::TOOL_METADATA_RESOURCE_TYPES;
    }

    /**
     * Get an instance of the extractor class for a metadataextractor.
     *
     * @param string $plugin the subplugin name of the metadataextractor.
     * @throws \tool_metadata\extraction_exception
     *
     * @return \tool_metadata\extractor instance of extractor for metadataextractor subplugin.
     */
    public static function get_extractor(string $plugin) {
        $extractorclass = '\\metadataextractor_' . $plugin . '\\extractor';

        if (class_exists($extractorclass)) {
            $extractor = new $extractorclass();
        } else {
            throw new extraction_exception('error:extractorclassnotfound', 'tool_metadata', '', $plugin);
        }

        return $extractor;
    }

    /**
     * Get extraction information about the status of metadata extraction for a Moodle resource by a specific
     * metadataextractor subplugin.
     *
     * @param object $resource the resource instance to get extraction for.
     * @param string $type the type of the resource to extract metadata for.
     * @param \tool_metadata\extractor $extractor instance of metadataextractor extractor to use.
     *
     * @return \tool_metadata\extraction the extraction record for a extraction of a resource by a specific subplugin.
     */
    public static function get_extraction($resource, string $type, extractor $extractor) : extraction {

        $extraction = new extraction($resource, $type, $extractor);

        return $extraction;
    }

    /**
     * Asynchronously extract metadata for a resource.
     *
     * @param object $resource the resource instance to get extraction for.
     * @param string $type the type of the resource to extract metadata for.
     * @param \tool_metadata\extractor $extractor instance of metadataextractor extractor to use.
     *
     * @return \tool_metadata\extraction the extraction record containing extraction status.
     */
    public static function async_metadata_extraction($resource, string $type, extractor $extractor) : extraction {

        $extraction = self::get_extraction($resource, $type, $extractor);

        $task = new metadata_extraction_task();
        // We can't pass the entire resource or extractor as custom data as they may not be json encodable,
        // depending on their structure so we pass the resource ID and plugin name of the extractor.
        $task->set_custom_data(['resourceid' => helper::get_resource_id($resource, $type), 'type' => $type,
            'plugin' => $extractor->get_name()]);
        // Queue the task first and then change status, in case queuing fails.
        \core\task\manager::queue_adhoc_task($task);
        $extraction->set('status', extraction::STATUS_ACCEPTED);
        $extraction->set('reason', get_string('status:extractionaccepted', 'tool_metadata'));
        $extraction->save();

        return $extraction;
    }

    /**
     * Is metadata extraction supported for this resource by a specific extractor?
     *
     * @param object $resource the resource instance to check.
     * @param string $type the type of the resource to check.
     * @param \tool_metadata\extractor $extractor instance of metadataextractor extractor to use.
     *
     * @return bool
     */
    public static function can_extract_metadata($resource, string $type, extractor $extractor) : bool {
        return $extractor->validate_resource($resource, $type);
    }

    /**
     * Extract metadata for a resource using a specific metadataextractor subplugin.
     *
     * @param object $resource the resource to extract metadata for.
     * @param string $type the resource type.
     * @param \tool_metadata\extractor $extractor instance of metadataextractor extractor to use.
     *
     * @return \tool_metadata\metadata|null the created metadata instance or null if no metadata.
     */
    public static function extract_metadata($resource, string $type, extractor $extractor) {
        $metadata = $extractor->extract_metadata($resource, $type);

        return $metadata;
    }
}
