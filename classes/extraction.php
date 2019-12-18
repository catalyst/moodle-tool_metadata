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

use coding_exception;
use stdClass;
use stored_file;
use tool_metadata\plugininfo\metadataextractor;

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
     * The extractions table name.
     */
    const TABLE = 'metadata_extractions';

    /**
     * extraction constructor.
     *
     * @param \stored_file $file file to extract metadata for.
     * @param string $plugin metadataextractor subplugin to use for extraction of metadata.
     */
    public function __construct(stored_file $file, string $plugin) {
        global $DB;

        $record = $DB->get_record(static::TABLE, ['contenthash' => $file->get_contenthash(), 'extractor' => $plugin]);

        if ($record) {
            $data = $record;
        } else {
            $data = new stdClass();
            $data->contenthash = $file->get_contenthash();
            $data->extractor = $plugin;
            $data->type = extraction::RESOURCE_TYPE_FILE;

            $extractor = $this->get_extractor($plugin);

            // Check if we already have extracted metadata for the file, in case the extraction record was deleted.
            if ($extractor->has_extracted_metadata_for_contenthash($file->get_contenthash())) {
                $data->status = extraction::STATUS_COMPLETE;
                $data->reason = get_string( 'status:extractioncomplete', 'tool_metadata');
            } else {
                $data->status = extraction::STATUS_NOT_FOUND;
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

    /**
     * Get the metadata object created by this extraction.
     *
     * @return false|\tool_metadata\metadata metadata object or false if extraction not complete.
     * @throws \coding_exception if associated extractor is not enabled.
     */
    public function get_metadata() {

        $extractor = $this->get_extractor();

        $metadata = $extractor->read_metadata($this->get('contenthash'));

        return $metadata;
    }

    /**
     * Get instance of extractor for a metadataextractor subplugin.
     *
     * @param string $plugin the metadataextractor subplugin name.
     *
     * @return \tool_metadata\extractor instance of a child class.
     * @throws \coding_exception if the passed in plugin is not enabled.
     */
    protected function get_extractor(string $plugin = '') {

        if (empty($plugin)) {
            $plugin = $this->get('extractor');
        }

        if (in_array($plugin, metadataextractor::get_enabled_plugins())) {
            $extractorclass = '\\metadataextractor_' . $plugin . '\\extractor';
            $extractor = new $extractorclass;
        } else {
            throw new coding_exception("Cannot get extractor: 'metadataextractor_$plugin' plugin is not enabled.");
        }

        return $extractor;
    }
}
