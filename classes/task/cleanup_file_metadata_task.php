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
 * The scheduled task for cleanup of metadata for removed files.
 *
 * @package    tool_metadata
 * @copyright  2020 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_metadata\task;

use core\task\scheduled_task;
use tool_metadata\extractor;
use tool_metadata\api;
use tool_metadata\plugininfo\metadataextractor;

defined('MOODLE_INTERNAL') || die();

/**
 * The scheduled task for cleanup of metadata for removed files.
 *
 * @package    tool_metadata
 * @copyright  2020 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_file_metadata_task extends scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     *
     */
    public function get_name() : string {
        return get_string('task:cleanupfilemetadata', 'tool_metadata');
    }

    /**
     * Get an array of records representing all files and their extractions to be processed.
     *
     * Note: These records are indexed by a concatenation of the id number of the file and the
     * plugginname of the metadataextractor subplugin conducting the extraction, eg. '44tika'.
     * If there is no extractions for a particular file id, the record is indexed by file id
     * and a string indicating that no extractions have been conducted, eg. '33none'.
     *
     * @param \tool_metadata\extractor $extractor the extractor to get deleted file contenthashes for.
     *
     * @return array
     */
    public function get_deleted_file_contenthashes(extractor $extractor) : array {
        global $DB;

        // We use a left outer join here to identify metadata records for which
        // any corresponding file records to that resourcehash have been deleted.
        $sql = "SELECT m.resourcehash
                FROM {" . $extractor->get_base_table() . "} m
                JOIN {metadata_extractions} e
                    ON m.resourcehash = e.resourcehash
                LEFT OUTER JOIN {files} f
                    ON e.resourceid = f.id
                WHERE (f.id IS NULL)
                    AND e.type = :file
                    AND e.extactor = :extractor";

        $params = [
            'file' => TOOL_METADATA_RESOURCE_TYPE_FILE,
            'extractor' => $extractor->get_name()
        ];

        $contenthashes = $DB->get_fieldset_sql($sql, $params);

        return $contenthashes;
    }

    /**
     * Do the job.
     * Throw exceptions on errors (the job will be retried).
     */
    public function execute() : void {
        global $DB;

        $enabledplugins = metadataextractor::get_enabled_plugins();

        if (empty($enabledplugins)) {
            mtrace('tool_metadata: No enabled metadata subplugins, file metadata cleanup skipped.');
        } else {
            foreach ($enabledplugins as $plugin) {
                $extractor = api::get_extractor($plugin);
                $contenthashes = $this->get_deleted_file_contenthashes($extractor);
                list($insql, $inparams) = $DB->get_in_or_equal($contenthashes);
                $DB->delete_records_select($extractor->get_base_table(), 'resourcehash ' . $insql, $inparams);
                mtrace('tool_metadata: Removed deleted file metadata for metadataextractor_' . $plugin);
            }
        }
    }
}
