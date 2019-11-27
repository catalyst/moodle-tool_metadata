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

defined('MOODLE_INTERNAL') || die();

/**
 * The adhoc task for asynchronous extraction of metadata.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class file_extraction_task extends adhoc_task {

    /**
     * Do the job.
     * Throw exceptions on errors (the job will be retried).
     */
    public function execute() {
        $data = $this->get_custom_data();

        $fs = get_file_storage();
        $file = $fs->get_file_by_id($data->fileid);

        $api = new api();
        $extraction = $api->get_file_extraction($file, $data->plugin);

        $extractorclass = '\\metadataextractor_' . $data->plugin . '\\extractor';
        $extractor = new $extractorclass();

        $extraction->set('status', extraction::STATUS_PENDING);
        $extraction->set('reason', get_string('status:extractioncommenced', 'tool_metadata'));
        $extraction->update();

        try {
            $extractor->create_file_metadata($file);
            $extraction->set('status', extraction::STATUS_COMPLETE);
            $extraction->set('reason', get_string('status:extractioncomplete', 'tool_metadata'));
        } catch (extraction_exception $e) {
            $extraction->set('status', extraction::STATUS_ERROR);
            $extraction->set('reason', get_string('error:extractionfailed', 'tool_metadata'));
            mtrace($e->getMessage());
            mtrace($e->getTraceAsString());
        } finally {
            $extraction->update();
        }
    }
}