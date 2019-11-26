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
 * The response object for Metadata API requests.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_metadata;

use stdClass;
use stored_file;

defined('MOODLE_INTERNAL') || die();

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
     * Resource type: file, extraction conducted on Moodle file resource.
     */
    const RESOURCE_TYPE_FILE = 'file';

    /**
     *
     */
    const TABLE = 'metadata_extractions';

    /**
     * Define properties of the persistent record.
     *
     * @return array
     */
    protected static function define_properties() {
        return array(
            'contenthash' => [
                'type' => PARAM_RAW,
                'description' => 'SHA1 hash of the content of the resource from which metadata is being extracted.'
            ],
            'type' => [
                'type' => PARAM_RAW,
                'choices' => [
                    self::RESOURCE_TYPE_FILE,
                ]
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
                'description' => 'The Moodle component name of the metadata extractor'
            ],
        );
    }

    public function get_metadata() {
        $extractorclass = '\\metadataextractor_' . $this->get('extractor') . '\\extractor';
        $extractor = new $extractorclass();

        $metadata = $extractor->read_metadata($this->get('contenthash'));

        return $metadata;
    }

}