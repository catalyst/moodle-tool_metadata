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
     * Do the job.
     * Throw exceptions on errors (the job will be retried).
     */
    public function execute() {
        // Expect custom data includes resource object, resource type and pluginname of extractor to use.
        // ['resourceid' => (int), 'type' => (string), 'plugin' => (string)].
        $data = $this->get_custom_data();

        // Build dependencies from the custom data, we can't inject them directly, as custom data only allows
        // json encodable objects.
        $extractor = api::get_extractor($data->plugin);
        $resource = helper::get_resource($data->resourceid, $data->type);
        $extraction = api::get_resource_extraction($resource, $data->type, $extractor);

        // The current extraction status doesn't matter, just start from the beginning and attempt to extract metadata.
        $extraction->set('status', extraction::STATUS_PENDING);
        $extraction->set('reason', get_string('status:extractioncommenced', 'tool_metadata'));
        $extraction->save(); // This will create the extraction record if it didn't previously exist in DB.

        mtrace('tool_metadata: Extracting metadata from resource...');
        mtrace("tool_metadata: metadataextractor - '" . $data->plugin . "'");
        mtrace("tool_metadata: resourceid - '" . $data->resourceid . "' type - '" . $data->type. "'.");

        if (!api::can_extract_metadata($resource, $data->type, $extractor)) {
            $extraction->set('status', extraction::STATUS_NOT_SUPPORTED);
            $extraction->set('reason', get_string('status:extractionnotsupported', 'tool_metadata', '',
                [ 'resourceid' => $data->resourceid, 'type' => $data->type, 'plugin' => $data->plugin ]));
        } else {
            // Try and extract metadata, capture all extraction exceptions here, so we know what went wrong.
            try {
                $existingmetadata = $extraction->get_metadata();
                $existingresourcehash = $extraction->get('resourcehash');
                $extractedmetadata = api::extract_metadata($resource, $data->type, $extractor);
                $extractedresourcehash = $extractedmetadata->get_resourcehash();

                if (!empty($existingmetadata) && !empty($existingmetadata->id)) {
                    mtrace('tool_metadata: Updating metadata...');
                    $metadata = api::update_metadata($existingmetadata->id, $extractedmetadata, $extractor);
                } else {
                    mtrace('tool_metadata: Creating metadata...');
                    $metadata = api::create_metadata($extractedmetadata, $extractor);
                }

                if ($existingresourcehash != $extractedresourcehash) {
                    // The resource content or content id has changed, update the extraction to reflect this.
                    $extraction->set('resourcehash', $extractedresourcehash);
                }

                if (!empty($metadata)) {
                    $extraction->set('status', extraction::STATUS_COMPLETE);
                    $extraction->set('reason', get_string('status:extractioncomplete', 'tool_metadata'));
                } else {
                    $extraction->set('status', extraction::STATUS_NOT_FOUND);
                    $extraction->set('reason', get_string('status:nometadata', 'tool_metadata',
                        ['resourceid' => $data->resourceid, 'type' => $data->type]));
                }

            } catch (extraction_exception $ex) {
                $extraction->set('status', extraction::STATUS_ERROR);
                $extraction->set('reason', $ex->getMessage());
                mtrace($ex->getMessage());
                if (debugging('', DEBUG_DEVELOPER)) {
                    mtrace(format_backtrace($ex->getTrace(), true));
                }
            }
        }

        $extraction->save();
        mtrace('tool_metadata: ' . $extraction->get('reason'));
    }
}