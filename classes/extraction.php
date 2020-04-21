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
 * Class representing the extraction of metadata for a resource by a particular metadata extractor.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_metadata;

use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/admin/tool/metadata/constants.php');

/**
 * Class representing the extraction of metadata for a resource by a particular metadata extractor.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class extraction extends \core\persistent {

    /**
     * Extraction status: extraction successfully completed.
     */
    const STATUS_COMPLETE = 200;

    /**
     * Extraction status: extraction currently in progress.
     */
    const STATUS_PENDING = 201;

    /**
     * Extraction status: extraction task created, not yet in progress.
     */
    const STATUS_ACCEPTED = 202;

    /**
     * Extraction status: no extracted metadata could be found.
     */
    const STATUS_NOT_FOUND = 404;

    /**
     * Extraction status: extraction complete, resource media type is not supported.
     */
    const STATUS_NOT_SUPPORTED = 415;

    /**
     * Extraction status: extraction completed with error.
     */
    const STATUS_ERROR = 500;

    /**
     * The extractions table name.
     */
    const TABLE = 'tool_metadata_extractions';

    /**
     * extraction constructor.
     *
     * @param object $resource an instance of a resource to extract metadata for.
     * @param string $type the type of resource metadata is being extracted for.
     * @param object $extractor instance of metadataextractor extractor to use.
     */
    public function __construct($resource, string $type, $extractor) {
        global $DB;

        $record = $DB->get_record(static::TABLE, [
            'resourceid' => helper::get_resource_id($resource, $type),
            'type' => $type,
            'extractor' => $extractor->get_name()
        ]);

        if (!empty($record)) {
            $data = $record;
        } else {
            $data = new stdClass();
            $data->resourceid = helper::get_resource_id($resource, $type);
            $data->resourcehash = helper::get_resourcehash($resource, $type);
            $data->type = $type;
            $data->extractor = $extractor->get_name();

            // Check if we already have extracted metadata for the resource, in case the extraction record was deleted.
            if ($extractor->has_metadata($data->resourcehash)) {
                $data->status = self::STATUS_COMPLETE;
                $data->reason = get_string( 'status:extractioncomplete', 'tool_metadata');
            } else {
                $data->status = self::STATUS_NOT_FOUND;
                $data->reason = get_string( 'status:extractionnotinitiated', 'tool_metadata');
            }
        }

        parent::__construct(0, $data);
    }

    /**
     * Define properties of the persistent record.
     *
     * @return array
     */
    protected static function define_properties() {
        return array(
            'resourcehash' => [
                'type' => PARAM_RAW,
                'description' => 'SHA1 hash of the content of the resource from which metadata is being extracted.'
            ],
            'resourceid' => [
                'type' => PARAM_INT,
            ],
            'type' => [
                'type' => PARAM_RAW,
                'choices' => \tool_metadata\helper::get_metadata_resource_types()
            ],
            'status' => [
                'type' => PARAM_INT,
                'choices' => [
                    self::STATUS_COMPLETE,
                    self::STATUS_PENDING,
                    self::STATUS_ACCEPTED,
                    self::STATUS_NOT_FOUND,
                    self::STATUS_NOT_SUPPORTED,
                    self::STATUS_ERROR,
                ],
                'default' => self::STATUS_NOT_FOUND,
            ],
            'reason' => [
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'extractor' => [
                'type' => PARAM_RAW,
                'description' => 'The subplugin name of the metadata extractor used for this extraction.'
            ],
        );
    }

    /**
     * Get the metadata object created by this extraction.
     *
     * @return \tool_metadata\metadata|null metadata object or null if no metadata.
     */
    public function get_metadata() {

        $extractor = \tool_metadata\api::get_extractor($this->get('extractor'));
        $metadata = $extractor->get_metadata($this->get('resourcehash'));

        return $metadata;
    }

    /**
     * Get the resource instance associated with this extraction.
     *
     * @return mixed
     * @throws \coding_exception
     * @throws \tool_metadata\extraction_exception
     */
    public function get_resource() {

        $resource = helper::get_resource($this->get('resourceid'), $this->get('type'));

        return $resource;
    }
}
