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
 * Abstract class for defining resource specific metadata extraction scheduled tasks.
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

global $CFG;
require_once($CFG->dirroot . '/admin/tool/metadata/constants.php');

/**
 * Abstract class for defining resource specific metadata extraction scheduled tasks.
 *
 * @package    tool_metadata
 * @copyright  2020 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class process_extractions_base_task extends scheduled_task {

    /**
     * The string resourcetype extraction task supports.
     */
    const RESOURCE_TYPE = '';

    /**
     * Get the resource type this process extractions task is for.
     *
     * @return string
     */
    public function get_resource_type() {
        return static::RESOURCE_TYPE;
    }

    /**
     * Get an array of records representing all resources and their extractions to be processed.
     * (If a resource does not have an extraction yet, extraction values should be empty).
     *
     * Note: These records are to be indexed by a concatenation of the id number of the resource and the
     * plugginname of the metadataextractor subplugin conducting the extraction, eg. '44tika'.
     *
     * @param array $extractors \tool_metadata\extractor[] of the extractors to use for metadata extraction,
     * indexed by subplugin name.
     *
     * @return array object[] of records containing resource and extraction information to process.
     * Each record should have the following shape: {
     *      uniqueid        => (string) concatenation of resourceid and extractor name
     *      resourceid      => (int) id of resource
     *      extractionid    => (int|null) id of extraction or null if no extraction record
     *      extractor       => (string) name of extractor
     *      resourcehash    => (string|null) resource hash or null in no extraction record
     *      status          => (int|null) extraction status or null if no extraction record
     *      timemodified    => (int|null) the time extraction was last modified or null if no extraction record
     * }
     */
    public function get_extractions_to_process(array $extractors = []) : array {
        global $DB;

        $startidconfig = 'process' . $this->get_resource_type() . 'startid';
        $startid = get_config('tool_metadata', $startidconfig);

        if (!$startid) {
            $startid = 0;
            set_config($startidconfig, $startid, 'tool_metadata');
        }

        $records = [];
        foreach ($extractors as $extractor) {
            $name = $extractor->get_name();

            // Build a unique id from the resource id and extractor plugin name.
            $uniqueid = $DB->sql_concat('r.id', "'" . $name . "'");

            // We use a left outer join here to capture resources which don't have extractions.
            $sql = "SELECT $uniqueid as uniqueid, r.id as resourceid, e.id as extractionid,
                    '$name' as extractor, e.resourcehash, e.status, e.timemodified
                FROM {" . helper::get_resource_table($this->get_resource_type()) . "} r
                LEFT OUTER JOIN {tool_metadata_extractions} e
                    ON r.id = e.resourceid
                    AND (e.type = :type OR e.type IS NULL)
                    AND (e.extractor = :extractor OR e.extractor IS  NULL)
                WHERE r.id > :startid";

            $params = [
                'extractor' => $name,
                'startid' => $startid,
                'type' => $this->get_resource_type(),
            ];

            // Add any conditions which need to be applied for this resource type to extractions.
            if ($conditions = static::get_resource_extraction_conditions('r')) {
                foreach ($conditions as $condition) {
                    $sql .= ' AND ' . $condition->sql;
                    $params = array_merge($params, $condition->params);
                }
            }

            $sql .= ' ORDER BY uniqueid';

            $maxprocesses = get_config('tool_metadata', 'max_extraction_processes');
            if (!empty($maxprocesses)) {
                $limitto = $maxprocesses;
            } else {
                $limitto = TOOL_METADATA_MAX_PROCESSES_DEFAULT;
            }

            $extractorrecords = $DB->get_records_sql($sql, $params, 0, $limitto);
            $records = array_merge($records, $extractorrecords);
        }

        $recordcount = count($records);

        if ($recordcount < $limitto) {
            // We reached the end of the resource table, start again from the beginning on next run.
            set_config($startidconfig, 0, 'tool_metadata');
        } else {
            // Set the startid for the next task run to the last id of this run.
            set_config($startidconfig, end($records)->resourceid, 'tool_metadata');
        }

        return $records;
    }

    /**
     * Get conditions to be applied when getting resource records to extract metadata for.
     * Override this method in extending classes to apply custom conditions specific to a resource
     * type.
     *
     * @param string $tablealias the table alias being used for the resource table.
     *
     * @return array $conditions object[] of object instances containing:
     *  {
     *      'sql' => (string) The SQL statement to add to where clause.
     *      'params => (array) Values for bound parameters in the SQL statement indexed by parameter name.
     *  }
     */
    protected function get_resource_extraction_conditions($tablealias) {
        $conditions = [];

        return $conditions;
    }

    /**
     * Get the total amount of extractions processed from status summary.
     *
     * @param object $statussummary object containing various status totals.
     *
     * @return int the total number of extractions.
     */
    public function calculate_total_extractions_processed($statussummary) : int {
        $statusarray = get_object_vars($statussummary);
        $values = array_values($statusarray);
        $total = array_sum($values);

        return $total;
    }

    /**
     * Process the resource extraction records and get statuses.
     *
     * @param array $records object[] the resource extraction records to process.
     * @param array $extractors \tool_metadata\extractor[] of the extractors to use for metadata extraction,
     * indexed by subplugin name.
     *
     * @return \stdClass $statussummary an object containing status information on extractions processed.
     */
    public function process_extractions(array $records = [], array $extractors = []) : stdClass {

        $statussummary = new stdClass();
        $statussummary->completed = 0; // Count of successfully completed metadata extractions.
        $statussummary->duplicates = 0; // Count of resources with same content found and skipped as a result.
        $statussummary->pending = 0; // Count of pending metadata extractions (those already queued but not completed.)
        $statussummary->queued = 0; // Count of metadata extractions we have queued.
        $statussummary->errors = 0; // Count of metadata extraction errors identified.
        $statussummary->unsupported = 0; // Count of resources for which extraction is not supported by a particular plugin.
        $statussummary->unknown = 0; // Count of resources, the metadata extraction of which is in an unknown state.

        $extractedresourcehashes = [];
        $processedresourcehashes = [];
        foreach ($extractors as $extractor) {
            // Get the resourcehashes we have completed metadata extraction for, to avoid processing duplicate content.
            $extractedresourcehashes[$extractor->get_name()] = $extractor->get_extracted_resourcehashes();
            // Track the resourcehashes as we queue extractions, to prevent queuing the same content twice.
            $processedresourcehashes[$extractor->get_name()] = [];
        }

        foreach ($records as $record) {

            $resource = helper::get_resource($record->resourceid, $this->get_resource_type());
            $resourcehash = helper::get_resourcehash($resource, $this->get_resource_type());

            if (in_array($resourcehash, $processedresourcehashes[$record->extractor])) {
                // Duplicate found, we have already processed this resourcehash, don't add to
                // processed hashes list, as we want to count all duplicates.
                $statussummary->duplicates++;
            } else if (in_array($resourcehash, $extractedresourcehashes[$record->extractor])) {
                // We already have extracted metadata for this resourcehash, mark this resourcehash
                // as processed so any subsequent occurrences will be noted as duplicates.
                $statussummary->completed++;
                $processedresourcehashes[$record->extractor][] = $resourcehash;
            } else if (!empty($record->status)) {
                // We have an extraction record for this resource, handle.
                $processedresourcehashes[$record->extractor][] = $resourcehash;

                switch ($record->status) {
                    case extraction::STATUS_PENDING :
                    case extraction::STATUS_ACCEPTED :
                        if ($record->timemodified < (time() - 86400)) {
                            // Extraction has been pending or was started over 24 hours ago,
                            // something probably went wrong, process again.
                            api::async_metadata_extraction($resource, $this->get_resource_type(),
                                $extractors[$record->extractor]);
                            $statussummary->queued++;
                        } else {
                            $statussummary->pending++;
                        }
                        break;

                    case extraction::STATUS_NOT_FOUND :
                        api::async_metadata_extraction($resource, $this->get_resource_type(),
                            $extractors[$record->extractor]);
                        $statussummary->queued++;
                        break;

                    case extraction::STATUS_NOT_SUPPORTED :
                        $statussummary->unsupported++;
                        break;

                    case extraction::STATUS_COMPLETE :
                        $statussummary->completed++;
                        break;

                    case extraction::STATUS_ERROR :
                    default :
                        $statussummary->errors++;
                        break;
                }
            } else {
                // No extraction record, queue up extraction.
                api::async_metadata_extraction($resource, $this->get_resource_type(), $extractors[$record->extractor]);
                $parsedrecordhashes[$extractor->get_name()][] = $resourcehash;
                $statussummary->queued++;
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

        // Filter out plugins which don't support url metadata extraction.
        foreach ($enabledplugins as $plugin) {
            $extractor = api::get_extractor($plugin);
            if ($extractor->supports_resource_type($this->get_resource_type())) {
                $extractors[$plugin] = $extractor;
            }
        }

        if (empty($extractors)) {
            mtrace('tool_metadata: No enabled metadata subplugins support ' . $this->get_resource_type() .
                ' extraction, ' . $this->get_resource_type() . ' metadata processing skipped.');
        } else {
            $recordstoprocess = $this->get_extractions_to_process($extractors);

            if (!empty($recordstoprocess)) {
                $processresults = $this->process_extractions($recordstoprocess, $extractors);

                mtrace('tool_metadata: ' . $this->get_resource_type() . ' completed extractions found = '
                    . $processresults->completed);
                mtrace('tool_metadata: ' . $this->get_resource_type() . ' duplicate resources found = '
                    . $processresults->duplicates);
                mtrace('tool_metadata: ' . $this->get_resource_type() . ' extractions queued = '
                    . $processresults->queued);
                mtrace('tool_metadata: ' . $this->get_resource_type() . ' extractions found already pending = '
                    . $processresults->pending);
                mtrace('tool_metadata: ' . $this->get_resource_type() . ' extractions not supported = '
                    . $processresults->unsupported);
                mtrace('tool_metadata: ' . $this->get_resource_type() . ' extraction errors identified = '
                    . $processresults->errors);
                mtrace('tool_metadata: ' . $this->get_resource_type() . ' extractions with unknown state = '
                    . $processresults->unknown);
                mtrace('tool_metadata: Total ' . $this->get_resource_type() . ' extractions processed = '
                    . $this->calculate_total_extractions_processed($processresults));

            } else {
                mtrace('tool_metadata: No ' . $this->get_resource_type() . ' resources found requiring extraction');
            }
        }
    }
}
