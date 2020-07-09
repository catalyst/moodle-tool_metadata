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
use tool_metadata\extractor;
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
     * Should this task process resources cyclically?
     * (ie. once all resources are processed, should processing start again from the beginning
     * and reprocess all resources?)
     */
    const IS_CYCLICAL = true;

    /**
     * Get the resource type this process extractions task is for.
     *
     * @return string
     */
    public function get_resource_type() : string {
        return static::RESOURCE_TYPE;
    }

    /**
     * Check if this task is cyclical.
     *
     * @return bool true if processing is cyclical, false otherwise.
     */
    public function is_cyclical() : bool {
        return static::IS_CYCLICAL;
    }

    /**
     * Get an array of records representing all resources and their extractions to be processed.
     * (If a resource does not have an extraction yet, extraction values should be empty).
     * Note: These records are to be indexed by a concatenation of the id number of the resource and the
     * plugginname of the metadataextractor subplugin conducting the extraction, eg. '44tika'.
     *
     * @param extractor $extractor \tool_metadata\extractor extractor to use for metadata extraction.
     * @param int $limitto the resource id limit of extractions to process up to.
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
    public function get_extractions_to_process(extractor $extractor, int $limitto) : array {
        global $DB;

        $records = [];

        if (!empty($limitto)) {
            $name = $extractor->get_name();
            $startid = $this->get_extractor_startid($name);

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
            $conditions = static::get_resource_extraction_conditions('r');
            foreach ($conditions as $condition) {
                $sql .= ' AND ' . $condition->sql;
                $params = array_merge($params, $condition->params);
            }

            // Add any filter values from tool_metadata settings which need to be applied.
            $filters = helper::get_resource_extraction_filters($this->get_resource_type());
            foreach ($filters as $index => $filter) {
                // Use index to ensure no conflict in bound param names.
                $param = 'filter' . $index;
                $sql .= ' AND ' . $DB->sql_equal('r.' . $filter->field, ':' . $param, true, true, true);
                $params = array_merge($params, [$param => $filter->value]);
            }

            $sql .= ' ORDER BY resourceid ASC';

            $records = $DB->get_records_sql($sql, $params, 0, $limitto);
        }

        return $records;
    }

    /**
     * Get the name of the plugin config which tracks the startid of extractions by this task for a specific extractor.
     *
     * @param string $extractorname the name of the extractor to get config name for.
     */
    protected function get_startid_config_name(string $extractorname) {
        return 'process_' . $this->get_resource_type() . '_' . $extractorname . '_startid';
    }

    /**
     * Get the startid for processing of resource extractions for a specific extractor.
     *
     * @param string $extractorname the name of the extractor to get resource startid for.
     *
     * @return int $startid the startid for processing of extractors extractions.
     */
    protected function get_extractor_startid(string $extractorname) {
        $startidconfig = $this->get_startid_config_name($extractorname);
        $startid = get_config('tool_metadata', $startidconfig);

        if (empty($startid)) {
            $highestid = extraction::get_highest_completed_resourceid($extractorname, $this->get_resource_type());
            $startid = !empty($highestid) ? $highestid : 0;
            $this->set_extractor_startid($extractorname, $startid);
        }

        return $startid;
    }

    /**
     * Set the startid for processing of extractions for a specific extractor.
     *
     * @param string $extractorname the name of the extractor to set resource startid for.
     * @param int $value the startid value to set.
     */
    protected function set_extractor_startid(string $extractorname, int $value) {
        $startidconfig = $this->get_startid_config_name($extractorname);
        set_config($startidconfig, $value, 'tool_metadata');
    }

    /**
     * Calculate how many extraction tasks we can queue per extractor.
     *
     * @param int $extractorcount the count of extractors we are extracting metadata for.
     *
     * @return int the count of available extraction slots in queue for each extractor.
     */
    public function calculate_extraction_limit_per_extractor(int $extractorcount) {
        global $DB;

        $maxprocesses = get_config('tool_metadata', 'max_extraction_processes');
        if (empty($maxprocesses)) {
            $maxprocesses = TOOL_METADATA_MAX_PROCESSES_DEFAULT;
        }

        $totalprocesseslimit = get_config('tool_metadata', 'total_extraction_processes');
        if (empty($totalprocesseslimit)) {
            $totalprocesseslimit = TOOL_METADATA_TOTAL_PROCESSED_LIMIT_DEFAULT;
        }

        $like = $DB->sql_like('classname', ':classname');
        $params = ['classname' => addslashes('%' . \tool_metadata\task\metadata_extraction_task::class . '%')];
        $currentprocesscount = $DB->count_records_select('task_adhoc', $like, $params);
        $availableslotstotal = $totalprocesseslimit - $currentprocesscount;
        $availableslotstotal = $availableslotstotal > 0 ? $availableslotstotal : 0;
        $availableslotsperextractor = (int) floor($availableslotstotal / $extractorcount);

        $limit = $availableslotsperextractor >= $maxprocesses ? $maxprocesses : $availableslotsperextractor;

        return $limit;
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
    public function get_resource_extraction_conditions($tablealias = '') {
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
            mtrace('tool_metadata: ' . get_string('task:noextractorssupporttype', 'tool_metadata', $this->get_resource_type()));
        } else {
            $recordstoprocess = [];
            $nextrunstartids = [];
            $limitto = $this->calculate_extraction_limit_per_extractor(count($extractors));

            $iscyclicalprocessingdisabled = (bool) get_config('tool_metadata', 'cyclical_processing_disabled');

            foreach ($extractors as $extractor) {
                $extractorrecords = $this->get_extractions_to_process($extractor, $limitto);

                if ($this->is_cyclical() && !$iscyclicalprocessingdisabled && count($extractorrecords) < $limitto) {
                    // We reached the end of the resource table, start again from the beginning on next run.
                    $nextrunstartids[$extractor->get_name()] = 0;
                } else if (!empty($extractorrecords)) {
                    // Set the startid for the next task run to the last id of this run.
                    $nextrunstartids[$extractor->get_name()] = end($extractorrecords)->resourceid;
                }

                $recordstoprocess = array_merge($recordstoprocess, $extractorrecords);
            }

            $processresults = $this->process_extractions($recordstoprocess, $extractors);

            // Don't update next run start id's until successful processing of results, in case of task failure.
            foreach ($nextrunstartids as $extractor => $nextrunstartid) {
                $this->set_extractor_startid($extractor, $nextrunstartid);
            }

            if (!empty($recordstoprocess)) {

                mtrace('tool_metadata: ' . $this->get_resource_type() . ' completed extractions found = ' .
                    $processresults->completed);
                mtrace('tool_metadata: ' . $this->get_resource_type() . ' duplicate resources found = ' .
                    $processresults->duplicates);
                mtrace('tool_metadata: ' . $this->get_resource_type() . ' extractions queued = ' .
                    $processresults->queued);
                mtrace('tool_metadata: ' . $this->get_resource_type() . ' extractions found already pending = ' .
                    $processresults->pending);
                mtrace('tool_metadata: ' . $this->get_resource_type() . ' extractions not supported = ' .
                    $processresults->unsupported);
                mtrace('tool_metadata: ' . $this->get_resource_type() . ' extraction errors identified = ' .
                    $processresults->errors);
                mtrace('tool_metadata: ' . $this->get_resource_type() . ' extractions with unknown state = ' .
                    $processresults->unknown);
                mtrace('tool_metadata: Total ' . $this->get_resource_type() . ' extractions processed = ' .
                    $this->calculate_total_extractions_processed($processresults));
            } else {
                mtrace('tool_metadata: ' . get_string('task:noextractionstoprocess', 'tool_metadata', $this->get_resource_type()));
            }
        }
    }
}
