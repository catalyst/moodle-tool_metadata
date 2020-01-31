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
 * The metadata base class.
 *
 * @package    metadataextractor_tika
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_metadata;

defined('MOODLE_INTERNAL') || die();

/**
 * The metadata base class.
 *
 * Metadata fields outside basic identifiers and creation/modification times must be defined in
 * metadataextractor subplugins extension of this class and raw metadata mapped to them via the
 * metadata_key_mapping method.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class metadata {

    /**
     * @var int metadata id in metadataextractor table.
     */
    public $id;

    /**
     * @var string SHA1 hash of the resource content or unique content identifier.
     */
    public $resourcehash;

    /**
     * @var int Unix epoch time metadata was created.
     */
    public $timecreated;

    /**
     * @var int Unix epoch time metadata was modified.
     */
    public $timemodified;

    /**
     * metadata constructor.
     *
     * @param string $resourcehash a unique SHA1 hash of the resource content or unique content identifier.
     * @param array|object $data a fieldset object or array of raw metadata values.
     * @param bool $triggercreation true if instance being created from raw extracted metadata or false if instance
     *      being created from stored record.
     */
    public function __construct($resourcehash, $data, $triggercreation = false) {

        if (!is_array($data)) {
            $data = get_object_vars($data);
        }

        // If this metadata hasn't been stored, it won't have an id.
        $this->id = array_key_exists('id', $data) ? $data['id'] : 0;

        if ($triggercreation) {
            $this->populate_from_raw_metadata($resourcehash, $data);
        } else {
            foreach ($data as $key => $value) {
                if (object_property_exists($this, $key)) {
                    $this->$key = $value;
                }
            }
        }
    }

    /**
     * Return the mapping of instantiating class attributes to potential raw metadata keys
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
    protected function metadata_key_map() {
        return [];
    }

    /**
     * Populate the attributes of this metadata instance from a raw associative array.
     *
     * @param string $resourcehash
     * @param array $data
     */
    protected function populate_from_raw_metadata(string $resourcehash, array $data) {
        $this->resourcehash = $resourcehash;

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

        if (array_key_exists('timecreated', $data)) {
            $this->timecreated = $data['timecreated'];
        } else {
            $this->timecreated = time();
        }
        $this->timemodified = time();
    }

    /**
     * Get this instance as an associative array.
     *
     * @return array of all contained metadata as [ $key => $value ].
     */
    public function get_associative_array(){
        $result = [];

        foreach (static::metadata_key_map() as $key => $unused) {
            $result[$key] = (!empty($this->$key)) ? $this->$key : null;
        }

        return $result;
    }

    /**
     * Get this instance as an a json encoded string.
     *
     * @return string json representation of metadata.
     */
    public function get_json(){
        return json_encode($this->get_associative_array());
    }

    /**
     * Get the contenthash associated with this metadata.
     *
     * @return string sha1 content hash.
     */
    public function get_resourcehash() {
        return $this->resourcehash;
    }

    /**
     * Get the metadata attribute a raw metadata key is mapped to.
     *
     * @param string $metadatakey the key to find attribute for.
     *
     * @return string|null the attribute name, null if no mapping.
     */
    public function get_attribute_key_mapped_to($metadatakey) {
        $result = null;

        foreach (static::metadata_key_map() as $attribute => $metadatakeys) {
            if (in_array($metadatakey, $metadatakeys)) {
                $result = $attribute;
                break;
            }
        }

        return $result;
    }

    /**
     * Does a raw metadata key have a mapping?
     *
     * @param string $metadatakey
     *
     * @return bool
     */
    public function is_key_mapped(string $metadatakey) : bool {
        $result = false;

        if (!empty($this->get_attribute_key_mapped_to($metadatakey))) {
            $result = true;
        }

        return $result;
    }
}
