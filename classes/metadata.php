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
     * Required: string - the table name where instance metadata is stored.
     */
    const TABLE = '';

    /**
     * @var int metadata id in metadataextractor table.
     */
    public $id;

    /**
     * @var string SHA1 hash of the resource content or unique content identifier.
     */
    private $resourcehash;

    /**
     * @var int Unix epoch time metadata record was created.
     */
    private $timecreated;

    /**
     * @var int Unix epoch time metadata record was modified.
     */
    private $timemodified;

    /**
     * metadata constructor.
     * Required: the ID of the existing metadata record to populate instance data from OR the resourcehash AND data
     * fieldset object or array to populate instance from.
     *
     * @param int $id the existing id of the metadata record for this instance, 0 (zero) if none.
     * @param string $resourcehash a unique SHA1 hash of the resource content or unique content identifier.
     * @param array|object $data a fieldset object or array of raw metadata values.
     *
     * @throws \tool_metadata\metadata_exception if metadata table doesn't exist.
     */
    public function __construct(int $id = 0, string $resourcehash = '', $data = []) {
        global $DB;

        if (!is_array($data)) {
            $data = get_object_vars($data);
        }

        if (!$DB->get_manager()->table_exists(static::TABLE)) {
            throw new metadata_exception('error:metadata:tablenotexists');
        }

        $this->populate($id, $resourcehash, $data);
    }

    /**
     * Populate the values of this metadata instance's variables.
     *
     * @param int $id the existing id of the metadata record for this instance, 0 (zero) if none.
     * @param string $resourcehash a unique SHA1 hash of the resource content or unique content identifier.
     * @param array|object $data a fieldset object or array of raw metadata values.
     *
     * @throws \tool_metadata\metadata_exception
     */
    protected function populate(int $id, string $resourcehash, $data) {

        // Populate with record data first if we have an ID or resourcehash.
        if (!empty($id)) {
            $this->populate_from_id($id);
        } else if (!empty($resourcehash)) {
            try {
                $this->populate_from_resourcehash($resourcehash);
            } catch (metadata_exception $exception) {
                if (empty($data)) {
                    throw new metadata_exception('error:metadata:cannotpopulate');
                }
            }
        } else if (array_key_exists('id', $data) && is_integer($data['id'])) {
            try {
                $this->populate_from_id($data['id']);
            } catch (metadata_exception $exception) {
                // ID key in raw data may be metadata itself, ignore.
            }
        }

        // Override record data with newly parsed data if present.
        if (!empty($data) && !empty($resourcehash)) {
            $this->populate_from_raw($resourcehash, $data);
        }

        if (empty($id) && empty($resourcehash) && empty($data)) {
            throw new metadata_exception('error:metadata:cannotpopulate');
        }
    }

    /**
     * Get the value of a property in object instance.
     *
     * @param string $property
     *
     * @return mixed
     * @throws \tool_metadata\metadata_exception
     */
    public function get(string $property) {
        if (property_exists(static::class, $property)) {
            return $this->$property;
        } else {
            throw new metadata_exception('error:metadata:propertynotexists');
        }
    }

    /**
     * Set the value of a property in object instance.
     *
     * @param string $property the property to set.
     * @param mixed $value the value to set.
     *
     * @return mixed the value set.
     * @throws \tool_metadata\metadata_exception
     */
    public function set(string $property, $value) {
        if (property_exists(static::class, $property)) {
            $this->$property = $value;
        } else {
            throw new metadata_exception('error:metadata:propertynotexists');
        }

        return $value;
    }

    /**
     * Get this metadata object as a standard object for database use.
     *
     * @return \stdClass
     */
    public function get_record() {
        $record = new \stdClass();

        if (!empty($this->id)) {
            $record->id = $this->id;
        } else {
            $record->id = 0;
        }

        $record->resourcehash = $this->resourcehash;
        $record->timecreated = $this->timecreated;
        $record->timemodified = $this->timemodified;

        $keys = array_keys(static::metadata_key_map());

        foreach ($keys as $key) {
            $record->$key = $this->$key;
        }

        return $record;
    }

    /**
     * Return the mapping of instantiating class variables to potential raw metadata keys
     * in order of priority from highest to lowest.
     *
     * Each metadata variable of this class must be included here so when populating data in this
     * instance we know what metadata keys map to which instance variables.
     * If a value cannot be found, a null value is populated, indicating that no data could be found
     * for that variable.
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
     * Get the table where this instance's data is stored.
     *
     * @return string the table name.
     */
    public function get_table() {
        return static::TABLE;
    }

    /**
     * Save this instance into the database.
     *
     * @return bool true on success.
     */
    public function save() {

        if (!empty($this->id)) {
            $this->update();
        } else {
            $this->create();
        }

        return true;
    }

    /**
     * Create the record for this instance in database.
     *
     * @return $this
     */
    public function create() {
        global $DB;

        $record = $this->get_record();

        if (empty($record->timecreated)) {
            $record->timecreated = $this->timecreated = time();
        }
        $record->timemodified = $this->timemodified = time();

        $id = $DB->insert_record($this->get_table(), $record);
        $this->id = $id;

        return $this;
    }

    /**
     * Read the data from database into this metadata instance.
     *
     * @throws \tool_metadata\metadata_exception if cannot read metadata from database.
     */
    public function read() {

        if (empty($this->id)) {
            throw new metadata_exception('error:metadata:noid');
        } else {
            $this->populate_from_id($this->id);
        }

        return $this;
    }

    /**
     * Update the record for this metadata instance in database.
     *
     * @return bool true on success.
     */
    public function update() {
        global $DB;

        $record = $this->get_record();

        if (!empty($record->id)) {
            $record->timemodified = time();
            $result = $DB->update_record($this->get_table(), $record);
        } else {
            throw new metadata_exception('error:metadata:noid');
        }

        return $result;
    }

    /**
     * Delete the data for this instance from database.
     *
     * @return bool true on success.
     *
     * @throws \tool_metadata\metadata_exception if
     */
    public function delete() {
        global $DB;

        if (!empty($this->id)) {
            $result = $DB->delete_records($this->get_table(), ['id' => $this->id]);
            $this->id = 0; // Set id to 0 (zero) as this instance no longer has associated record.
        } else {
            throw new metadata_exception('error:metadata:noid');
        }

        return $result;
    }

    /**
     * Populate the variables of this metadata instance from a raw associative array.
     *
     * @param string $resourcehash
     * @param array $data
     */
    protected function populate_from_raw(string $resourcehash, array $data) : void {
        $this->resourcehash = $resourcehash;

        foreach (static::metadata_key_map() as $variable => $metadatakeys) {
            $metadatavalue = null;
            $i = 0;

            while ($i < count($metadatakeys) && empty($metadatavalue)) {
                if (isset($data[$metadatakeys[$i]])) {
                    $metadatavalue = $data[$metadatakeys[$i]];
                }
                $i++;
            }
            $this->$variable = $metadatavalue;
        }
    }

    /**
     * Populate the variables of this metadata instance from an existing database record by id.
     *
     * @param int $id the id of the record to populate metadata from.
     *
     * @throws \tool_metadata\metadata_exception if record with corresponding ID could not be found.
     */
    protected function populate_from_id(int $id) : void {
        global $DB;

        $record = $DB->get_record(static::TABLE, ['id' => $id]);
        if (empty($record)) {
            throw new metadata_exception('error:metadata:populateidnotfound', 'tool_metadata', '', $id);
        }

        foreach ((array) $record as $property => $value) {
            $this->$property = $value;
        }
    }

    /**
     * Populate the variables of this metadata instance from an existing database record by resourcehash.
     *
     * @param int $id the id of the record to populate metadata from.
     *
     * @throws \tool_metadata\metadata_exception if record with corresponding resourcehash could not be found.
     */
    protected function populate_from_resourcehash(string $resourcehash) : void {
        global $DB;

        $record = $DB->get_record(static::TABLE, ['resourcehash' => $resourcehash]);
        if (empty($record)) {
            throw new metadata_exception('error:metadata:populateresourcehashnotfound', 'tool_metadata', '', $resourcehash);
        }

        foreach ((array) $record as $property => $value) {
            $this->$property = $value;
        }
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
     * Get the metadata variable a raw metadata key is mapped to.
     *
     * @param string $metadatakey the key to find variable for.
     *
     * @return string|null the variable name, null if no mapping.
     */
    public function get_variable_key_mapped_to($metadatakey) {
        $result = null;

        foreach (static::metadata_key_map() as $variable => $metadatakeys) {
            if (in_array($metadatakey, $metadatakeys)) {
                $result = $variable;
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

        if (!empty($this->get_variable_key_mapped_to($metadatakey))) {
            $result = true;
        }

        return $result;
    }
}
