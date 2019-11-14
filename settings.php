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

defined('MOODLE_INTERNAL') || die();

// Add the metadata plugin type to modules and a page to manage extractor subplugins.
$ADMIN->add('modules', new admin_category('metadata',
    get_string('pluginname', 'tool_metadata')));
$temp = new admin_settingpage('managemetadataextractors',
    get_string('settings:extractor:manage', 'tool_metadata'));
$temp->add(new \tool_metadata\admin_setting_manage_metadataextractor_plugins());
$ADMIN->add('metadata', $temp);

// Load the settings.php scripts for each metadataextractor submodule.
$plugins = core_plugin_manager::instance()->get_subplugins_of_plugin('tool_metadata');
core_collator::asort_objects_by_property($plugins, 'displayname');
foreach ($plugins as $plugin) {
    /** @var \tool_metadata\plugininfo\metadataextractor $plugin */
    $plugin->load_settings($ADMIN, 'metadata', $hassiteconfig);
}


