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

use core\task\scheduled_task;
use stdClass;
use tool_metadata\api;
use tool_metadata\extraction;
use tool_metadata\plugininfo\metadataextractor;

defined('MOODLE_INTERNAL') || die();

/**
 * The scheduled task for extraction of metadata from files.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_file_extractions_task extends scheduled_task {

    /**
     * Maximum files to process.
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
        return get_string('task:processfiles', 'tool_metadata');
    }

    /**
     * Get an array of records representing all files and their extractions to be processed.
     *
     * @param array $plugins string[] of plugins to process file metadata extractions for.
     *
     * @return array
     */
    public function get_file_extractions_to_process(array $plugins = []) : array {
        global $DB;

        $startid = get_config('tool_metadata', 'processfilesstartid');

        if (!$startid) {
            $startid = 0;
            set_config('processfilesstartid', $startid, 'tool_metadata');
        }

        $enabledplugins = "'" . implode("', '", $plugins) . "'";
        // Build a unique id from the file id and extractor plugin name (or a constant string if no extractions).
        $uniqueid = $DB->sql_concat('f.id', "COALESCE(e.extractor, '" . self::NO_EXTRACTOR . "')");

        // We use a left outer join here to capture all files which don't have extractions for any
        // of the enabled extractors as well as those which have extractions for some or all of the
        // enabled metadata extractors.
        $sql = "SELECT $uniqueid AS uniqueid, f.id as fileid, e.id as extractionid, 
                    f.contenthash, e.extractor, e.status, e.timemodified
                FROM {files} f
                LEFT OUTER JOIN {metadata_extractions} e
                    ON f.contenthash = e.contenthash
                WHERE (e.extractor IN ($enabledplugins) OR e.extractor IS NULL)
                    AND f.id > ?";

        $filerecords = $DB->get_records_sql($sql, [$startid], 0, self::MAX_PROCESSES);

        return $filerecords;
    }

    /**
     * Process the file extraction records and get statuses.
     *
     * @param array $records the file extraction records to process.
     * @param array $plugins the plugins to process file extractions for.
     *
     * @return \stdClass $statussummary an object containing status information on file extractions processed.
     */
    public function process_file_extractions(array $records = [], array $plugins = []) : stdClass {

        $statussummary = new stdClass();
        $statussummary->completed = 0; // Count of successfully completed metadata extractions.
        $statussummary->queued = 0; // Count of queued metadata extractions.
        $statussummary->errors = 0; // Count of metadata extraction errors identified.
        $statussummary->unknown = 0; // Count of metadata extractions with an unknown state.

        $parsedcontenthashes = [];
        foreach ($plugins as $plugin) {
            $parsedcontenthashes[$plugin] = [];
        }

        $fs = get_file_storage();
        $api = new api();

        foreach ($records as $record) {

            $file = $fs->get_file_by_id($record->fileid);

            if (!empty($record->extractor)) {
                if (in_array($record->contenthash, $parsedcontenthashes[$record->extractor])) {
                    // We have already parsed the extraction of this file by this extractor.
                    continue;
                } else {
                    $parsedcontenthashes[$record->extractor][] = $record->contenthash;
                }

                switch ($record->status) {
                    case extraction::STATUS_PENDING :
                        if ($record->timemodified < (time() - 86400)) {
                            // This has been pending for over 24 hours, something probably went wrong, process again.
                            $api->extract_file_metadata($file, $plugin);
                        }
                        $statussummary->queued++;
                        break;

                    case extraction::STATUS_NOT_FOUND :
                        $api->extract_file_metadata($file, $plugin);
                        $statussummary->queued++;
                        break;

                    case extraction::STATUS_ERROR :
                        $statussummary->errors++;
                        break;

                    case extraction::STATUS_ACCEPTED :
                        $statussummary->queued++;
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
                foreach ($plugins as $plugin) {
                    if (!in_array($record->contenthash, $parsedcontenthashes[$plugin])){
                        $api->extract_file_metadata($file, $plugin);
                        $parsedcontenthashes[$plugin][] = $record->contenthash;
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
    public function execute() : void {

        $enabledplugins = metadataextractor::get_enabled_plugins();

        if (empty($enabledplugins)) {
            mtrace('tool_metadata: No enabled metadata subplugins, file metadata processing skipped.');
        } else {
            $recordstoprocess = $this->get_file_extractions_to_process($enabledplugins);
            $processresults = $this->process_file_extractions($recordstoprocess, $enabledplugins);

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
            mtrace('tool_metadata: Count of queued extractions processed = ' . $processresults->queued);
            mtrace('tool_metadata: Count of extraction errors identified = ' . $processresults->errors);
            mtrace('tool_metadata: Count of extractions with unknown state = ' . $processresults->unknown);
        }
    }
}
