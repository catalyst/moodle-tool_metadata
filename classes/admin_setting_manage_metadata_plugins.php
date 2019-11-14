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
 * The main api for handling file metadata.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_metadata;

use admin_setting_manage_plugins;

defined('MOODLE_INTERNAL') || die();

/**
 * Class for management of metadata subplugins.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_manage_metadata_plugins extends admin_setting_manage_plugins {

    /**
     * Get the title of the admin section.
     *
     * @return string
     */
    public function get_section_title() {
        return get_string('settings:extractor:plural', 'tool_metadata');
    }

    /**
     * Get the plugin type this admin setting applies to.
     *
     * @return string
     */
    public function get_plugin_type() {
        return 'metadataextractor';
    }

    /**
     * Get the title of the information column for this admin setting.
     *
     * @return string
     * @throws \coding_exception
     */
    public function get_info_column_name() {
        return get_string('settings:supportedfileextensions', 'tool_metadata');
    }

    /**
     * Get the value to display in the information column for this subplugin.
     *
     * @param $plugininfo
     *
     * @return string
     */
    public function get_info_column($plugininfo) {
        $extensions = $plugininfo->get_supported_file_extensions();
        return implode(', ', $extensions);
    }

}