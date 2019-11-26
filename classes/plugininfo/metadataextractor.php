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
 * Defines class used for plugin info for metadata extractor subplugins.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_metadata\plugininfo;

defined('MOODLE_INTERNAL') || die();

/**
 * Defines class used for plugin info for metadata extractor subplugins.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class metadataextractor extends \core\plugininfo\base {

    /**
     * Metadataextractor plugins can be uninstalled.
     *
     * @return bool
     */
    public function is_uninstall_allowed() {
        return true;
    }

    /**
     * Returns the node name used in admin settings menu for this plugin settings.
     *
     * @return string node name.
     */
    public function get_settings_section_name() {
        return 'metadataextractor' . $this->name;
    }

    /**
     * Loads plugin settings to the settings tree.
     *
     * This function usually includes settings.php file in plugins folder.
     * Alternatively it can create a link to some settings page (instance of admin_externalpage)
     *
     * @param \part_of_admin_tree $adminroot
     * @param string $parentnodename
     * @param bool $hassiteconfig whether the current user has moodle/site:config capability
     */
    public function load_settings(\part_of_admin_tree $adminroot, $parentnodename, $hassiteconfig) {
        global $CFG, $USER, $DB, $OUTPUT, $PAGE; // In case settings.php wants to refer to them.
        $ADMIN = $adminroot; // May be used in settings.php.
        $plugininfo = $this; // Also can be used inside settings.php.

        if (!$this->is_installed_and_upgraded()) {
            return;
        }

        if (!$hassiteconfig) {
            return;
        }

        $section = $this->get_settings_section_name();

        $settings = null;

        if (file_exists($this->full_path('settings.php'))) {
            $settings = new \admin_settingpage($section, $this->displayname, 'moodle/site:config',
                $this->is_enabled() === false);
            include_once($this->full_path('settings.php')); // This may also set $settings to null.
        }

        if ($settings) {
            $ADMIN->add($parentnodename, $settings);
        }
    }

    /**
     * Finds all enabled plugins, the result may include missing plugins.
     *
     * @return array|null of enabled plugins $pluginname => $pluginname, null means unknown.
     */
    public static function get_enabled_plugins() {
        $installedplugins = \core_plugin_manager::instance()->get_installed_plugins('metadataextractor');
        $plugins = [];

        if ($sortedenabledplugins = get_config('tool_metadata', 'metadataextractor_plugins_priority_order')) {
            foreach (explode(',', $sortedenabledplugins) as $plugin) {
                if (in_array($plugin, array_keys($installedplugins))){
                    $plugins[$plugin] = $plugin;
                }
            }
        }
        return $plugins;
    }


    public function set_enabled($newstate = true) {
        $enabled = self::get_enabled_plugins();

        if (array_key_exists($this->name, $enabled) == $newstate) {
            // Nothing to do.
            return;
        }

        if ($newstate) {
            // Enable metadataextractor plugin.
            $plugins = $this->pluginman->get_plugins_of_type('metadataextractor');
            if (!array_key_exists($this->name, $plugins)) {
                // Can not be enabled.
                return;
            }
            $enabled[$this->name] = $this->name;
            self::set_enabled_plugins($enabled);
        } else {
            // Disable converter plugin.
            unset($enabled[$this->name]);
            self::set_enabled_plugins($enabled);
        }
    }

    /**
     * Set the enabled plugins.
     *
     * @param array|string $list the names of plugins to set as enabled.
     */
    public static function set_enabled_plugins($list) {
        if (empty($list)) {
            $list = [];
        } else if (!is_array($list)) {
            $list = explode(',', $list);
        }
        if ($list) {
            $plugins = \core_plugin_manager::instance()->get_installed_plugins('metadataextractor');
            $list = array_intersect($list, array_keys($plugins));
        }
        set_config('metadataextractor_plugins_priority_order', join(',', $list), 'tool_metadata');
        \core_plugin_manager::reset_caches();
    }

    /**
     * Get URL used for management of plugins of this type.
     *
     * @return \moodle_url
     */
    public static function get_manage_url() {
        return new \moodle_url('/admin/settings.php', array('section' => 'managemetadataextractors'));
    }

    /**
     * Get the plugin supported file extensions.
     *
     * @return array (string) of file extensions this subplugin supports.
     */
    public function get_supported_file_extensions() {
        $result = [];
//        $extractorclass = '\\metadataextractor\\' . $this->name . '\\extractor';
//        if (class_exists($extractorclass) && is_a($extractorclass, 'extractor_strategy', true)) {
//            $result = $extractorclass::get_supported_file_extensions();
//        }
        return $result;
    }

}
