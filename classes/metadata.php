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
 * Define the core metadata model for all resources.
 *
 * @package    metadataextractor_tika
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_metadata;

defined('MOODLE_INTERNAL') || die();

/**
 * The core metadata model for all resources.
 *
 * This model follows a modified version of Dublin Core tailored for Moodle.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class metadata {

    public $id;

    public $contenthash;

    public $timecreated;

    public $timemodified;

    /**
     * metadata constructor.
     *
     * @param string $contenthash a unique SHA1 hash of resource content.
     * @param array|object $data a fieldset object or array of raw metadata values.
     * @param bool $israw true if instance being created from raw extracted metadata or false if instance
     *      being created from stored record.
     */
    public function __construct($contenthash, $data, $israw = false) {

        if (!is_array($data)) {
            $data = get_object_vars($data);
        }

        if ($israw) {
            $this->populate_from_raw_metadata($contenthash, $data);
        } else {
            foreach ($data as $key => $value) {
                if (object_property_exists($this, $key)) {
                    $this->$key = $value;
                }
            }
        }
    }

    /**
     * Return the mapping of instantiating class attributes to potential metadata keys
     * in order of priority from highest to lowest.
     *
     * Each metadata attribute of this class must be included here so when populating data in this
     * instance we know what metadata keys map to which instance attributes.
     * If a value cannot be found, a null value is populated, indicating that no data could be found
     * for that attribute.
     *
     * Example:
     *  [
     *      'author' => [
     *           'Author', 'meta:author', 'Creator', 'meta:creator', 'dc:creator',
     *       ],
     *      'title' => [
     *           'Title', 'meta:title', 'dc:title',
     *       ]
     *  ]
     *
     * @return array
     */
    protected static function metadata_key_map() {
        return [];
    }

    /**
     * Populate the attributes of this metadata instance.
     *
     * @param string $contenthash
     * @param array $data
     */
    protected function populate_from_raw_metadata(string $contenthash, $data) {
        $this->contenthash = $contenthash;

        // If this metadata hasn't been stored, it won't have an id.
        $this->id = array_key_exists('id', $data) ? $data['id'] : 0;

        foreach (static::metadata_key_map() as $attribute => $metadatakeys) {
            $metadatavalue = null;
            $i = 0;

            while ($i < count($metadatakeys) && empty($metadatavalue)) {
                if (isset($data[$metadatakeys[$i]])) {
                    $metadatavalue = $data[$metadatakeys[$i]];
                }
                $i++;
            }

            $this->$attribute = $metadatavalue;
        }

        $this->timecreated = time();
        $this->timemodified = time();
    }

    /**
     * @return array of all contained metadata as [ $key => $value ].
     */
    public function get_associative_array(){
        $result = [];

        foreach (static::metadata_key_map() as $key => $unused) {
            $result[$key] = $this->$key;
        }

        return $result;
    }

    /**
     * @return string json representation of metadata.
     */
    public function get_json(){
        return json_encode($this->get_associative_array());
    }
}
