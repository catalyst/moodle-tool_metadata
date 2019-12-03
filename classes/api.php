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

use stdClass;
use stored_file;
use tool_metadata\plugininfo\metadataextractor;
use tool_metadata\task\file_extraction_task;

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
     * Get an metadata from each extractor for a Moodle file.
     *
     * @param \stored_file $file
     *
     * @return array $metadataarray /tool_metadata/metadata[]
     */
    public function get_file_metadata(stored_file $file) {
        $extractions = $this->get_file_extractions($file);
        $metadataarray = [];

        foreach ($extractions as $extraction) {
            if ($extraction->get('status') == extraction::STATUS_COMPLETE) {
                $metadata = $extraction->get_metadata();
            }
            if ($metadata) {
                $metadataarray[] = $metadata;
            } else {
                // We should never get here unless a metadataextractor record has been
                // manually deleted without updating the corresponding extraction
                // record, but just in case, let's extract the metadata again.
                $this->extract_file_metadata($file, $extraction->get('extractor'));
            }
        }

        return $metadataarray;
    }

    /**
     * Get metadata extractions for a Moodle file(s).
     *
     * @param \stored_file $file the file to get extractions for.
     * @param array|string $plugins an array of metadataextractor plugin names (or singular string if only one plugin)
     * @param bool $triggerextraction
     *
     * @return array|\tool_metadata\extraction single extraction or array of extractions for each enabled metadata extractor.
     */
    public function get_file_extractions(stored_file $file, $plugins = [],
                                                         bool $triggerextraction = true) {
        $extractions = [];

        if (!is_array($plugins)) {
            $plugins = [$plugins];
        }

        if (empty($plugins)) {
            $enabledplugins = metadataextractor::get_enabled_plugins();
        } else {
            $enabledplugins = array_intersect($plugins, metadataextractor::get_enabled_plugins());
        }

        foreach ($enabledplugins as $plugin) {
            $extraction = $this->get_file_extraction($file, $plugin);

            if ($extraction->get('status') == extraction::STATUS_NOT_FOUND && $triggerextraction) {
                $extraction = $this->extract_file_metadata($file, $plugin);
            }

            $extractions[$plugin] = $extraction;
        }

        return $extractions;
    }

    /**
     * Get information about the status of metadata extraction for a Moodle file by a specific
     * metadataextractor subplugin.
     *
     * @param \stored_file $file
     * @param string $plugin
     *
     * @return \tool_metadata\extraction
     */
    public function get_file_extraction(stored_file $file, string $plugin) {

        $extraction = new extraction($file, $plugin);

        return $extraction;
    }

    /**
     * Extract metadata for a Moodle file using a specific metadata extractor.
     *
     * @param \stored_file $file
     * @param string $plugin the metadataextractor subplugin name.
     *
     * @return \tool_metadata\extraction
     */
    public function extract_file_metadata(stored_file $file, string $plugin) {

        $extraction = $this->get_file_extraction($file, $plugin);

        $task = new file_extraction_task();
        $task->set_custom_data(['fileid' => $file->get_id(), 'plugin' => $plugin]);
        \core\task\manager::queue_adhoc_task($task);
        $extraction->set('status', extraction::STATUS_ACCEPTED);
        $extraction->set('reason', get_string('status:extractionaccepted', 'tool_metadata'));
        $extraction->save();

        return $extraction;
    }

}
