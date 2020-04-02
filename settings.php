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
 * Admin settings for component 'tool_metadata'.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use tool_metadata\admin_setting_configtextarea_json;
use tool_metadata\admin_setting_manage_metadataextractor_plugins;

global $CFG, $ADMIN;
require_once($CFG->dirroot . '/admin/tool/metadata/constants.php');

// Add the metadata plugin type to modules and a page to manage extractor subplugins.
$ADMIN->add('modules', new admin_category('metadata',
    get_string('pluginname', 'tool_metadata')));

$temp = new admin_settingpage('metadatasettings',
    get_string('settings:manage', 'tool_metadata'));

// Setting for managing subplugins.
$temp->add(new admin_setting_heading('managemetadataextractors',
    get_string('settings:manageextractors', 'tool_metadata'), ''));
$temp->add(new admin_setting_manage_metadataextractor_plugins());

// Settings for managing extraction process.
$temp->add(new admin_setting_heading('managemetadataextractionfilters',
    get_string('settings:manageextraction', 'tool_metadata'), ''));
$temp->add(new admin_setting_configtext('tool_metadata/max_extraction_processes',
    get_string('settings:maxextractionprocesses', 'tool_metadata'),
    get_string('settings:maxextractionprocesses_help', 'tool_metadata'),
    TOOL_METADATA_MAX_PROCESSES_DEFAULT, PARAM_INT));
$temp->add(new admin_setting_configtextarea_json('tool_metadata/extraction_filters',
    get_string('settings:extractionfilters', 'tool_metadata'),
    get_string('settings:extractionfilters_help', 'tool_metadata'), '[ ]', PARAM_RAW));
$ADMIN->add('metadata', $temp);

// Load the settings.php scripts for each metadataextractor submodule.
$plugins = core_plugin_manager::instance()->get_subplugins_of_plugin('tool_metadata');
core_collator::asort_objects_by_property($plugins, 'displayname');
foreach ($plugins as $plugin) {
    /** @var \tool_metadata\plugininfo\metadataextractor $plugin */
    $plugin->load_settings($ADMIN, 'metadata', $hassiteconfig);
}


