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

use core_form\filetypes_util;
use stored_file;
use tool_metadata\plugininfo\metadataextractor;

defined('MOODLE_INTERNAL') || die();

/**
 * The main api for handling metadata.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {

    /**
     * Moodle resource type - file.
     */
    const RESOURCE_TYPE_FILE = 'file';

    /**
     * Moodle resource type - URL link to an external resouce.
     */
    const RESOURCE_TYPE_URL = 'url';

    /**
     * Get metadata for a Moodle file(s).
     *
     * @param \stored_file $file the file to get metadata for.
     *
     * @return array|\tool_metadata\response single response or array of response for each enabled metadata extractor.
     */
    public static function get_file_metadata(stored_file $file, array $plugins = []) {

        $responses = [];
        $enabledplugins = metadataextractor::get_enabled_plugins();

        if (empty($plugins) && !empty($enabledplugins)) {
            $plugins = $enabledplugins;
        } else {
            $response = new response();
            $response->status = response::EXTRACTION_STATUS_ERROR;
            $response->reason = get_string('error:noenabledextractors', 'tool_metadata', $plugin);
            $responses[] = $response;
        }

        foreach ($plugins as $plugin) {
            if (!in_array($plugin, $enabledplugins)) {
                $response = new response();
                $response->plugin = $plugin;
                $response->status = response::EXTRACTION_STATUS_ERROR;
                $response->reason = get_string('error:pluginnotenabled', 'tool_metadata', $plugin);
            } else {
                $extractorclass = "\\$plugin\\extractor";
                $extractor = new $extractorclass();

                $response = $extractor->get_file_metadata($file);

                if ($response->status == response::EXTRACTION_STATUS_ERROR) {
                    $response = $extractor->create_file_metadata($file);
                }
            }

            $responses[$plugin] = $response;
        }

        if (count($responses) == 1) {
            $responses = reset($responses);
        }

        return $responses;
    }

    /**
     * Get the current status of metadata extraction for a resource.
     *
     * @param string $contenthash the unique contenthash of the resource.
     * @param string $plugin the metadataextractor plugin to check status of extraction for.
     *
     * @return bool|int a \tool_metadata\response status code.
     */
    public function get_metadata_extraction_status(string $contenthash, string $plugin) {
        global $DB;

        $status = false;

        $record = $DB->get_record('metadata_extraction_status',
            [ 'contenthash' => $contenthash, 'extractor' => $plugin,], 'status');

        if ($record) {
            $status = $record->status;
        }
        return $status;
    }

}