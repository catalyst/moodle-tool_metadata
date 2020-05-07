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
 * Scheduled task for cleaning up metadata for deleted resources.
 *
 * @package    tool_metadata
 * @copyright  2020 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_metadata\task;

use core\task\scheduled_task;
use tool_metadata\extraction;
use tool_metadata\extractor;
use tool_metadata\api;
use tool_metadata\helper;
use tool_metadata\plugininfo\metadataextractor;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task for cleaning up metadata for deleted resources.
 *
 * @package    tool_metadata
 * @copyright  2020 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_metadata_task extends scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     *
     */
    public function get_name() {
        return get_string('task:cleanupmetadata', 'tool_metadata');
    }

    /**
     * Get an array of resourcehashes representing all resources which have been deleted.
     *
     * @param \tool_metadata\extractor $extractor the extractor to get deleted resourcehashes for.
     *
     * @return array string[] of resourcehashes.
     */
    public function get_deleted_resourcehashes(extractor $extractor) : array {
        global $DB;

        $resourcehashes = [];

        foreach ($extractor->get_supported_resource_types() as $resourcetype) {
            // We use a left outer join here to identify metadata records for which
            // any corresponding resource records to that resourcehash have been deleted.
            $sql = "SELECT m.resourcehash
                    FROM {" . $extractor->get_base_table() . "} m
                    JOIN {tool_metadata_extractions} e
                        ON m.resourcehash = e.resourcehash
                    LEFT OUTER JOIN {" . helper::get_resource_table($resourcetype) . "} r
                        ON e.resourceid = r.id
                    WHERE (r.id IS NULL)
                        AND e.type = :type
                        AND e.extractor = :extractor";

            $params = [
                'type' => $resourcetype,
                'extractor' => $extractor->get_name()
            ];

            $typeresourcehashes = $DB->get_fieldset_sql($sql, $params);
            $resourcehashes = array_merge($resourcehashes, $typeresourcehashes);
        }

        return $resourcehashes;
    }


    /**
     * Do the job.
     * Throw exceptions on errors (the job will be retried).
     */
    public function execute() {
        global $DB;

        $enabledplugins = metadataextractor::get_enabled_plugins();

        if (empty($enabledplugins)) {
            mtrace('tool_metadata: No enabled metadata subplugins, metadata cleanup skipped.');
        } else {
            foreach ($enabledplugins as $plugin) {
                $deletecount = 0;
                $extractor = api::get_extractor($plugin);
                $resourcehashes = $this->get_deleted_resourcehashes($extractor);
                if (!empty($resourcehashes)) {
                    foreach ($resourcehashes as $resourcehash) {
                        $metadata = $extractor->get_metadata($resourcehash);
                        $metadata->delete();
                        $deletecount++;
                    }
                    // Delete the extraction records associated with resourcehashes too.
                    list($insql, $inparams) = $DB->get_in_or_equal($resourcehashes);
                    $DB->delete_records_select(extraction::TABLE, 'resourcehash ' . $insql, $inparams);
                    mtrace("tool_metadata: Deleted $deletecount metadata records for metadataextractor_$plugin");
                } else {
                    mtrace("tool_metadata: No metadata to cleanup for metadataextractor_$plugin");
                }
            }
        }
    }
}
