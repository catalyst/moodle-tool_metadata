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
 * The adhoc task for asynchronous extraction of metadata.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_metadata\task;

use core\task\adhoc_task;
use tool_metadata\api;
use tool_metadata\extraction;
use tool_metadata\extraction_exception;
use tool_metadata\helper;

defined('MOODLE_INTERNAL') || die();

/**
 * The adhoc task for asynchronous extraction of metadata for a resource.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class metadata_extraction_task extends adhoc_task {

    /**
     * Validate that all custom data required params are available for extraction.
     *
     * @param object $data the custom data object for task.
     *
     * @return bool true if all required params included, false otherwise.
     */
    private function validate_custom_data($data) : bool {
        $valid = true;

        foreach (['resourceid', 'type', 'plugin'] as $property) {
            if (!object_property_exists($data, 'resourceid') || empty($data->resourceid)) {
                $valid = false;
            }
        }

        return $valid;
    }

    /**
     * Do the job.
     * Throw exceptions on errors (the job will be retried).
     *
     * @throws \moodle_exception when a failure extracting metadata or creating metadata records.
     */
    public function execute() {
        // Expect custom data includes resource object, resource type and pluginname of extractor to use.
        // ['resourceid' => (int), 'type' => (string), 'plugin' => (string)].
        $data = $this->get_custom_data();

        if (!$this->validate_custom_data($data)) {
            mtrace("tool_metadata: Invalid extraction parameters, extraction can not be completed, removing task from queue.");
            return;
        }

        // Build dependencies from the custom data, we can't inject them directly, as custom data only allows
        // json encodable objects.
        try {
            $extractor = api::get_extractor($data->plugin);
        } catch (extraction_exception $exception) {
            // Exit early, the extractor has been uninstalled or removed somehow so this
            // extraction task will never work.
            mtrace("tool_metadata: Extractor $data->plugin not found, removing task from queue.");
            return;
        }

        $resource = helper::get_resource((int) $data->resourceid, $data->type);

        if (empty($resource)) {
            // Exit early, the resource instance has been removed and cannot be processed.
            mtrace("tool_metadata: $data->type resource id: $data->resourceid could not be found, " .
                'removing task from queue.');
            return;
        }

        $extraction = api::get_extraction($resource, $data->type, $extractor);

        // The current extraction status doesn't matter, just start from the beginning and attempt to extract metadata.
        $extraction->set('status', extraction::STATUS_PENDING);
        $extraction->set('reason', get_string('status:extractioncommenced', 'tool_metadata'));
        $extraction->save(); // This will create the extraction record if it didn't previously exist in DB.

        mtrace('tool_metadata: Attempting to extract metadata from resource...');
        mtrace("tool_metadata: metadataextractor - '" . $data->plugin . "'");
        mtrace("tool_metadata: resourceid - '" . $data->resourceid . "' type - '" . $data->type. "'.");

        if (!api::can_extract_metadata($resource, $data->type, $extractor)) {
            $extraction->set('status', extraction::STATUS_NOT_SUPPORTED);
            $extraction->set('reason', get_string('status:extractionnotsupported', 'tool_metadata',
                [ 'resourceid' => $data->resourceid, 'type' => $data->type, 'plugin' => $data->plugin ]));
        } else {
            // Try and extract metadata, capture all extraction exceptions here, so we know what went wrong.
            try {
                $metadata = api::extract_metadata($resource, $data->type, $extractor);

                if (!empty($metadata)) {
                    if ($metadata->has_record()) {
                        mtrace('tool_metadata: Updating metadata...');
                        $metadata->update();
                    } else {
                        mtrace('tool_metadata: Creating metadata...');
                        $metadata->create();
                    }
                    $extraction->set('status', extraction::STATUS_COMPLETE);
                    $extraction->set('reason', get_string('status:extractioncomplete', 'tool_metadata'));
                } else {
                    $extraction->set('status', extraction::STATUS_NOT_FOUND);
                    $extraction->set('reason', get_string('status:nometadata', 'tool_metadata',
                        ['resourceid' => $data->resourceid, 'type' => $data->type]));
                }
            } catch (\moodle_exception $ex) {
                $extraction->set('status', extraction::STATUS_ERROR);
                $extraction->set('reason', $ex->getMessage());
                mtrace($ex->getMessage());
                if (debugging('', DEBUG_DEVELOPER)) {
                    mtrace(format_backtrace($ex->getTrace(), true));
                }

                $faildelaythreshold = get_config('tool_metadata', 'faildelay_threshold');
                if (empty($faildelaythreshold)) {
                    $faildelaythreshold = TOOL_METADATA_FAIL_DELAY_THRESHOLD_DEFAULT;
                }

                if ($this->get_fail_delay() >= $faildelaythreshold) {
                    $extraction->set('status', extraction::STATUS_NOT_SUPPORTED);
                    $extraction->set('reason', get_string('status:extractionnotsupported', 'tool_metadata',
                        [ 'resourceid' => $data->resourceid, 'type' => $data->type, 'plugin' => $data->plugin ]));
                } else {
                    // Update extraction status and rethrow exception to trigger failed task.
                    $extraction->save();
                    throw $ex;
                }
            }
        }
        $extraction->save();
        mtrace('tool_metadata: ' . $extraction->get('reason'));
    }
}
