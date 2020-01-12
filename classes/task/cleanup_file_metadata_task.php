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
 * The scheduled task for extraction of metadata for files.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_metadata\task;

use core\task\scheduled_task;
use tool_metadata\plugininfo\metadataextractor;

defined('MOODLE_INTERNAL') || die();

/**
 * The scheduled task for extraction of metadata for files.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
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
     * @param array $plugins string[] of plugins to process file metadata extractions for.
     *
     * @return array
     */
    public function get_deleted_file_contenthashes($extractor) : array {
        global $DB;

        // We use a left outer join here to identify metadata records for which
        // the corresponding file has been deleted, or has been changed so the
        // contenthash has changed, requiring reprocessing of the metadata.
        $sql = "SELECT m.contenthash
                FROM {" . $extractor::METADATA_TABLE . "} m
                LEFT OUTER JOIN {files} f
                    ON m.contenthash = f.contenthash
                WHERE (f.contenthash IS NULL)";

        $contenthashes = $DB->get_fieldset_sql($sql);

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
                $extractorclass = '\\metadataextractor_' . $plugin . '\\extractor';
                $extractor = new $extractorclass;
                $contenthashes = $this->get_deleted_file_contenthashes($extractor);
                list($insql, $inparams) = $DB->get_in_or_equal($contenthashes);
                $DB->delete_records_select($extractor::METADATA_TABLE, 'contenthash ' . $insql, $inparams);
                mtrace('tool_metadata: Deleted file metadata for metadataextractor_' . $plugin);
            }
        }
    }
}
