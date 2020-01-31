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
 * The scheduled task for extraction of metadata for urls.
 *
 * @package    tool_metadata
 * @copyright  2020 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_metadata\task;

use stdClass;
use core\task\scheduled_task;
use tool_metadata\api;
use tool_metadata\extraction;
use tool_metadata\helper;
use tool_metadata\plugininfo\metadataextractor;

defined('MOODLE_INTERNAL') || die();

/**
 * The scheduled task for extraction of metadata for urls.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_url_extractions_task extends scheduled_task {

    /**
     * Maximum file extractions to process.
     */
    const MAX_PROCESSES = 400;

    /**
     * String indicating no extractions found for a file.
     */
    const NO_EXTRACTOR = 'none';

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     *
     */
    public function get_name() : string {
        return get_string('task:processurls', 'tool_metadata');
    }

    /**
     * Get an array of records representing all urls and their extractions to be processed.
     *
     * Note: These records are indexed by a concatenation of the id number of the file and the
     * plugginname of the metadataextractor subplugin conducting the extraction, eg. '44tika'.
     * If there is no extractions for a particular file id, the record is indexed by file id
     * and a string indicating that no extractions have been conducted, eg. '33none'.
     *
     * @param array $extractors string[] of the extractors to use for file metadata extraction, indexed by subplugin name.
     *
     * @return array
     */
    public function get_url_extractions_to_process(array $extractors = []) : array {
        global $DB;

        $startid = get_config('tool_metadata', "processurlsstartid");

        if (!$startid) {
            $startid = 0;
            set_config("processurlsstartid", $startid, 'tool_metadata');
        }

        $urlrecords = [];
        foreach ($extractors as $extractor) {
            $name = $extractor->get_name();

            // Build a unique id from the file id and extractor plugin name.
            $uniqueid = $DB->sql_concat('u.id', "'" . $name . "'");
            // Only handle http(s) urls, ftp not supported.
            $urlishttp = $DB->sql_like('u.externalurl', ':httplike', false, false);

            // We use a left outer join here to capture files which don't have extractions for the extractor.
            $sql = "SELECT $uniqueid as uniqueid, u.id as urlid, e.id as extractionid, 
                    e.resourcehash, e.extractor, e.status, e.timemodified
                FROM {url} u
                LEFT OUTER JOIN {metadata_extractions} e
                    ON u.id = e.resourceid
                    AND (e.type = :type OR e.type IS NULL)
                    AND (e.extractor = :extractor OR e.extractor IS  NULL)
                WHERE u.id > :startid
                    AND $urlishttp";

            $params = [
                'extractor' => $name,
                'startid' => $startid,
                'type' => TOOL_METADATA_RESOURCE_TYPE_URL,
                'httplike' => 'http%'
            ];

            $extractorrecords = $DB->get_records_sql($sql, $params, $startid, self::MAX_PROCESSES);
            $urlrecords = array_merge($urlrecords, $extractorrecords);
        }

        return $urlrecords;
    }

    /**
     * Process the url extraction records and get statuses.
     *
     * @param array $records the url extraction records to process.
     * @param array $extractors string[] of the extractors to use for file metadata extraction, indexed by subplugin name.
     *
     * @return \stdClass $statussummary an object containing status information on file extractions processed.
     */
    public function process_url_extractions(array $records = [], array $extractors = []) : stdClass {

        $statussummary = new stdClass();
        $statussummary->completed = 0; // Count of successfully completed metadata extractions.
        $statussummary->pending = 0; // Count of pending metadata extractions (those already queued but not completed.)
        $statussummary->queued = 0; // Count of queued metadata extractions.
        $statussummary->errors = 0; // Count of metadata extraction errors identified.
        $statussummary->unsupported = 0; // Count of files for which extraction is not supported by a particular plugin.
        $statussummary->unknown = 0; // Count of metadata extractions with an unknown state.

        $parsedrecordhashes = [];
        foreach ($extractors as $extractor) {
            $parsedrecordhashes[$extractor->get_name()] = $extractor->get_extracted_resourcehashes();
        }

        foreach ($records as $record) {

            $url = helper::get_resource($record->urlid, TOOL_METADATA_RESOURCE_TYPE_URL);

            if (!empty($record->extractor)) {
                if (in_array($record->resourcehash, $parsedrecordhashes[$record->extractor])) {
                    // We have already parsed the extraction of this file by this extractor.
                    continue;
                } else {
                    $parsedrecordhashes[$record->extractor][] = $record->resourcehash;
                }

                switch ($record->status) {
                    case extraction::STATUS_PENDING :
                    case extraction::STATUS_ACCEPTED :
                        if ($record->timemodified < (time() - 86400)) {
                            // Extraction has been pending or was started over 24 hours ago,
                            // something probably went wrong, process again.
                            api::async_metadata_extraction($url, TOOL_METADATA_RESOURCE_TYPE_FILE, $extractors[$record->extractor]);
                            $statussummary->queued++;
                        } else {
                            $statussummary->pending++;
                        }
                        break;

                    case extraction::STATUS_NOT_FOUND :
                        api::async_metadata_extraction($url, TOOL_METADATA_RESOURCE_TYPE_FILE, $extractors[$record->extractor]);
                        $statussummary->queued++;
                        break;

                    case extraction::STATUS_NOT_SUPPORTED :
                        $statussummary->unsupported++;
                        break;

                    case extraction::STATUS_ERROR :
                        $statussummary->errors++;
                        break;

                    case extraction::STATUS_COMPLETE :
                        $statussummary->completed++;
                        break;

                    default :
                        $statussummary->unknown++;
                        break;

                }
            } else {
                // We don't have any extractions for this file, so process with all enabled extractors.
                foreach ($extractors as $extractor) {
                    if (!in_array($record->resourcehash, $parsedrecordhashes[$extractor->get_name()])){
                        api::async_metadata_extraction($url, TOOL_METADATA_RESOURCE_TYPE_URL, $extractor);
                        $parsedrecordhashes[$extractor->get_name()][] = $record->resourcehash;
                        $statussummary->queued++;
                    }
                }
            }
        }

        return $statussummary;
    }

    /**
     * Do the job.
     * Throw exceptions on errors (the job will be retried).
     */
    public function execute() {

        $enabledplugins = metadataextractor::get_enabled_plugins();
        $extractors = [];

        // Filter out plugins which don't support file metadata extraction.
        foreach ($enabledplugins as $plugin) {
            $extractor = api::get_extractor($plugin);
            if ($extractor->supports_resource_type(TOOL_METADATA_RESOURCE_TYPE_FILE)) {
                $extractors[$plugin] = $extractor;
            }
        }

        if (empty($extractors)) {
            mtrace('tool_metadata: No enabled metadata subplugins support file extraction, file metadata processing skipped.');
        } else {
            $recordstoprocess = $this->get_url_extractions_to_process($extractors);
            $processresults = $this->process_url_extractions($recordstoprocess, $extractors);

            $recordcount = count($recordstoprocess);

            if ($recordcount < self::MAX_PROCESSES) {
                // We reached the end of the {files} table, start again from the beginning on next run.
                set_config('processfilesstartid', 0, 'tool_metadata');
            } else {
                // Set the startid for the next task run to the last id of this run.
                // (This may cause double processing of some extractions, but it will avoid skipping any
                // extractions for the last file of this run.)
                set_config('processfilesstartid', end($recordstoprocess)->fileid, 'tool_metadata');
            }

            mtrace('tool_metadata: Count of completed extractions processed = ' . $processresults->completed);
            mtrace('tool_metadata: Count of extractions queued = ' . $processresults->queued);
            mtrace('tool_metadata: Count of extractions not supported by a particular plugin = ' . $processresults->unsupported);
            mtrace('tool_metadata: Count of extraction errors identified = ' . $processresults->errors);
            mtrace('tool_metadata: Count of extractions with unknown state = ' . $processresults->unknown);
        }
    }
}
