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

namespace tool_metadata;

use stored_file;

defined('MOODLE_INTERNAL') || die();

/**
 * The main api for handling file metadata.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class api {

    const METADATA_TYPE_MIMETYPE_GROUP_MAP = [
        'video' => ['video', 'html_video', 'web_video'],
        'audio' => ['audio', 'html_audio', 'web_audio', 'html_track'],
        'image' => ['image', 'web_image'],
        'archive' => ['archive'],
        'text' => ['web_file', 'document', 'spreadsheet'],
        'presentation' => ['presentation'],
    ];

    /**
     * Get metadata for a Moodle file.
     *
     * @param \stored_file $file
     *
     * @return \tool_metadata\metadata_model
     */
    public static function get_metadata(stored_file $file, string $plugin = '') {

        $pluginman = \core_plugin_manager::instance();
        $plugininfo = $pluginman->get_plugin_info('metadataextractor_' . $plugin);
        $fileextension = pathinfo($file->get_filename(), PATHINFO_EXTENSION);

        $enabledplugins = $plugininfo->get_enabled_plugins();

        if (empty($enabledplugins)) {
            $extractor = new extractor();
        } else if (!empty($plugin)) {
            // If an extractor plugin is specified, use this to extract the data.
            if ($plugininfo->is_enabled() && in_array($fileextension, $plugininfo->get_supported_file_extensions())) {
                $extractorstrategy = '\\metadataextractor\\' . $plugin . '\\extractor';
                $extractor = new $extractorstrategy();
            } else {
                // TODO: Make this error message more meaningful and contextual.
                print_error('Plugin not enabled or file extension not supported.');
            }
        } else {
            // Use the highest priority enabled plugin if we didn't specify one.
            $extractorstrategy = '\\metadataextractor\\' . reset($enabledplugins) . '\\extractor';
            $extractor = new $extractorstrategy();
        }

        $metadata = $extractor->get_metadata($file);

        return $metadata;
    }

}