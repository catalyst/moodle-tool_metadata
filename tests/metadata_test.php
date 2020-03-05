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
 * tool_metadata metadata tests.
 *
 * @package    tool_metadata
 * @copyright  2020 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/admin/tool/metadata/constants.php');
require_once($CFG->dirroot . '/admin/tool/metadata/tests/mock_metadataextractor_metadata.php');
require_once($CFG->dirroot . '/admin/tool/metadata/tests/mock_metadataextractor_extractor.php');

/**
 * tool_metadata metadata tests.
 *
 * @package    tool_metadata
 * @copyright  2020 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      tool_metadata
 */
class tool_metadata_metadata_testcase extends advanced_testcase {

    public function setUp() {
        global $DB;

        $this->resetAfterTest();

        // Create a table for mock metadataextractor subplugin.
        $dbman = $DB->get_manager();
        $table = new \xmldb_table(\metadataextractor_mock\metadata::TABLE);
        // Add mandatory fields.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('resourcehash', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null, 'id');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'date');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timecreated');
        // Add the fields used in mock class.
        $table->add_field('author', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'subject');
        $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'description');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        };
    }

    /**
     * Test creating metadata record.
     */
    public function test_create() {
        global $DB;

        // Emulate metadataextractor returned raw metadata.
        $rawdata = [];
        $rawdata['meta:creator'] = 'Moodle';
        $rawdata['meta:title'] = 'Test title';

        // Emulate resourcehash from resource content.
        $resourcehash = sha1(random_string());

        $metadata = new \metadataextractor_mock\metadata(0, $resourcehash, $rawdata);
        $metadata->create();

        // Creating record should populate metadata id.
        $this->assertNotEmpty($metadata->id);

        // Metadata database record should contain mapped metadata.
        $actual = $DB->get_record($metadata->get_table(), ['id' => $metadata->id]);
        $this->assertEquals($rawdata['meta:creator'], $actual->author);
        $this->assertEquals($rawdata['meta:title'], $actual->title);
    }

    /**
     * Test updating metadata record.
     */
    public function test_update() {
        global $DB;

        // Emulate metadataextractor returned raw metadata.
        $rawdata = [];
        $rawdata['meta:creator'] = 'Moodle';
        $rawdata['meta:title'] = 'Test title';

        // Emulate resourcehash from resource content.
        $resourcehash = sha1(random_string());

        $metadata = new \metadataextractor_mock\metadata(0, $resourcehash, $rawdata);
        $metadata->create();

        // Update the details in metadata instance.
        $metadata->set('author', 'Rick Sanchez');
        $metadata->set('title', 'Rick and Morty\'s adventures');

        $result = $metadata->update();

        $this->assertTrue($result);

        // Metadata database record should contain newly mapped metadata.
        $actual = $DB->get_record($metadata->get_table(), ['id' => $metadata->id]);
        $this->assertEquals('Rick Sanchez', $actual->author);
        $this->assertEquals('Rick and Morty\'s adventures', $actual->title);

        // Use a new resourcehash to prevent population of metadata instance ID from database record.
        $resourcehash = sha1(random_string());

        // Should not be able to update a metadata instance's record when it has no id.
        $metadata = new \metadataextractor_mock\metadata(0, $resourcehash, $rawdata);
        $this->expectException(\tool_metadata\metadata_exception::class);
        $metadata->update();
    }

    /**
     * Test reading metadata from the database.
     */
    public function test_read() {
        global $DB;

        // Emulate metadataextractor returned raw metadata.
        $rawdata = [];
        $rawdata['meta:creator'] = 'Moodle';
        $rawdata['meta:title'] = 'Test title';

        // Emulate resourcehash from resource content.
        $resourcehash = sha1(random_string());

        $metadata = new \metadataextractor_mock\metadata(0, $resourcehash, $rawdata);
        $metadata->create();

        // Read metadata should reflect the data in record.
        $actual = $metadata->read();
        $this->assertEquals($rawdata['meta:creator'], $actual->author);
        $this->assertEquals($rawdata['meta:title'], $actual->title);

        // Manually update database record for metadata.
        $DB->update_record($metadata->get_table(),
            ['id' => $metadata->id, 'author' => 'Rick Sanchez', 'title' => 'Rick and Morty\'s adventures']);

        // Read metadata should reflect updated database record.
        $actual = $metadata->read();
        $this->assertEquals('Rick Sanchez', $actual->author);
        $this->assertEquals('Rick and Morty\'s adventures', $actual->title);
    }

    /**
     * Test saving the state of metadata instance to database.
     */
    public function test_save() {
        global $DB;

        // Emulate metadataextractor returned raw metadata.
        $rawdata = [];
        $rawdata['meta:creator'] = 'Moodle';
        $rawdata['meta:title'] = 'Test title';

        // Emulate resourcehash from resource content.
        $resourcehash = sha1(random_string());

        $metadata = new \metadataextractor_mock\metadata(0, $resourcehash, $rawdata);
        $result = $metadata->save();

        $this->assertTrue($result);
        $this->assertNotEquals(0, $metadata->id);

        $metadata->set('title', 'New title');

        $result = $metadata->save();

        $this->assertTrue($result);
        $this->assertEquals('New title', $metadata->title);
        // Saving should update database record.
        $record = $DB->get_record($metadata->get_table(), ['id' => $metadata->id]);
        $this->assertEquals('New title', $record->title);
    }

    /**
     * Test deleting record associated with metadata.
     */
    public function test_delete() {
        global $DB;

        // Emulate metadataextractor returned raw metadata.
        $rawdata = [];
        $rawdata['meta:creator'] = 'Moodle';
        $rawdata['meta:title'] = 'Test title';

        // Emulate resourcehash from resource content.
        $resourcehash = sha1(random_string());

        $metadata = new \metadataextractor_mock\metadata(0, $resourcehash, $rawdata);

        $metadata->create();
        $id = $metadata->id;

        $result = $metadata->delete();
        $this->assertTrue($result);

        // The instance id should be 0 (zero) when record deleted.
        $this->assertEquals(0, $metadata->id);

        // The record should be removed from the database.
        $this->assertEmpty($DB->get_record($metadata->get_table(), ['id' => $id]));
    }

    /**
     * Test getting the table associated with metadata.
     */
    public function test_get_table() {

        // Emulate metadataextractor returned raw metadata.
        $rawdata = [];
        $rawdata['meta:creator'] = 'Moodle';
        $rawdata['meta:title'] = 'Test title';

        // Emulate resourcehash from resource content.
        $resourcehash = sha1(random_string());

        $metadata = new metadataextractor_mock\metadata(0, $resourcehash, $rawdata);

        // Table returned should be that of the extending class, not abstract parent \tool_metadata\metadata class.
        $this->assertEquals(\metadataextractor_mock\metadata::TABLE, $metadata->get_table());
        $this->assertNotEquals(\tool_metadata\metadata::TABLE, $metadata->get_table());
    }

    /**
     * Test getting metadata instance as a record.
     */
    public function test_get_record() {

        // Emulate metadataextractor returned raw metadata.
        $rawdata = [];
        $rawdata['meta:creator'] = 'Moodle';
        $rawdata['meta:title'] = 'Test title';

        // Emulate resourcehash from resource content.
        $resourcehash = sha1(random_string());

        $metadata = new metadataextractor_mock\metadata(0, $resourcehash, $rawdata);

        $actual = $metadata->get_record();

        $this->assertEquals(0, $actual->id);
        $this->assertEquals($rawdata['meta:creator'], $actual->author);
        $this->assertEquals($rawdata['meta:title'], $actual->title);
        $this->assertEquals($resourcehash, $actual->resourcehash);
    }

    /**
     * Date provider of mock raw metadata.
     *
     * @return array [
     *      string $variable the name of the metadata variable in mock_metadataextractor_metadata
     *      string $rawkey the key received in extracted metadata
     *      string $rawvalue the value received in extracted metadata
     *      bool $variableexists Does mock_metadataextractor_metadata class have this variable?
     * ]
     */
    public function raw_metadata_provider() {
        return [
            ['author', 'meta:author', 'Rick Sanchez', true],
            ['title', 'dc:title', 'Get Schwifty', true],
            ['description', 'meta:description', 'A book about stuff', false],
        ];
    }

    /**
     * @dataProvider raw_metadata_provider
     *
     * Test getting the class variable which a metadata key is mapped to.
     */
    public function test_get_variable_key_mapped_to($variable, $rawkey, $rawvalue, $variableexists) {
        $rawdata = [$rawkey => $rawvalue];
        $contenthash = sha1(random_string());
        $metadata = new \metadataextractor_mock\metadata(0, $contenthash, $rawdata);
        $actual = $metadata->get_variable_key_mapped_to($rawkey);

        if ($variableexists) {
            $this->assertEquals($variable, $actual);
        } else {
            $this->assertEmpty($actual);
        }
    }

    /**
     * @dataProvider raw_metadata_provider
     *
     * Test checking if a key is mapped in the metadata instance.
     */
    public function test_is_key_mapped($unused, $rawkey, $rawvalue, $variableexists) {
        $rawdata = [$rawkey => $rawvalue];
        $contenthash = sha1(random_string());
        $metadata = new \metadataextractor_mock\metadata(0, $contenthash, $rawdata);
        $actual = $metadata->is_key_mapped($rawkey);

        $this->assertEquals($variableexists, $actual);
    }

    /**
     * Test populating metadata instance, called from constructor.
     */
    public function test_populate() {
        global $DB;

        // Emulate metadataextractor returned raw metadata.
        $rawdata = [];
        $rawdata['meta:creator'] = 'Moodle';
        $rawdata['meta:title'] = 'Test title';

        // Emulate resourcehash from resource content.
        $resourcehash = sha1(random_string());

        // Insert existing record for testing metadata construction from existing record.
        $id = $DB->insert_record(metadataextractor_mock\metadata::TABLE,
            ['author' => $rawdata['meta:creator'], 'title' => $rawdata['meta:title'], 'resourcehash' => $resourcehash]);

        // Should be able to populate metadata instance by explicit ID.
        $metadata = new metadataextractor_mock\metadata($id);

        $this->assertEquals($id, $metadata->get('id'));
        $this->assertEquals($rawdata['meta:creator'], $metadata->get('author'));
        $this->assertEquals($rawdata['meta:title'], $metadata->get('title'));
        $this->assertEquals($resourcehash, $metadata->get_resourcehash());

        // Should be able to populate metadata instance by resourcehash.
        $metadata = new metadataextractor_mock\metadata(0, $resourcehash);

        $this->assertEquals($id, $metadata->get('id'));
        $this->assertEquals($rawdata['meta:creator'], $metadata->get('author'));
        $this->assertEquals($rawdata['meta:title'], $metadata->get('title'));
        $this->assertEquals($resourcehash, $metadata->get_resourcehash());

        $rawdata['id'] = $id;
        // Should be able to populate metadata by ID contained in data parameter.
        $metadata = new metadataextractor_mock\metadata(0, $resourcehash, $rawdata);

        $this->assertEquals($rawdata['id'], $metadata->get('id'));
        $this->assertEquals($rawdata['meta:creator'], $metadata->get('author'));
        $this->assertEquals($rawdata['meta:title'], $metadata->get('title'));
        $this->assertEquals($resourcehash, $metadata->get_resourcehash());

        $updatedrawdata = [];
        $updatedrawdata['id'] = $id + 999; // ID which shouldn't exist yet.
        $updatedrawdata['meta:creator'] = 'Moodle 2.0';
        $updatedrawdata['meta:title'] = 'Updated title';

        // Should be able to populate metadata by explicit ID and raw data.
        $metadata = new metadataextractor_mock\metadata($id, $resourcehash, $updatedrawdata);

        // Raw data ID should not override explicit ID.
        $this->assertEquals($rawdata['id'], $metadata->get('id'));
        $this->assertNotEquals($updatedrawdata['id'], $metadata->get('id'));
        // Populating by ID and raw metadata together should override record values with raw values.
        $this->assertEquals($updatedrawdata['meta:creator'], $metadata->get('author'));
        $this->assertEquals($updatedrawdata['meta:title'], $metadata->get('title'));
        $this->assertNotEquals($rawdata['meta:creator'], $metadata->get('author'));
        $this->assertNotEquals($rawdata['meta:title'], $metadata->get('title'));
        $this->assertEquals($resourcehash, $metadata->get_resourcehash());

        $resourcehash = sha1(random_string()); // Set to resourcehash which shouldn't exist yet.

        // Raw data ID should be ignored when populating if no metadata record exists for that ID.
        $metadata = new metadataextractor_mock\metadata(0, $resourcehash, $updatedrawdata);
        $this->assertEquals(0, $metadata->get('id'));
        $this->assertNotEquals($updatedrawdata['id'], $metadata->get('id'));
        $this->assertEquals($updatedrawdata['meta:creator'], $metadata->get('author'));
        $this->assertEquals($updatedrawdata['meta:title'], $metadata->get('title'));
        $this->assertEquals($resourcehash, $metadata->get_resourcehash());

        // Should not be able to populate from new resourcehash with no data.
        $this->expectException(\tool_metadata\metadata_exception::class);
        $unused = new metadataextractor_mock\metadata(0, $resourcehash);
    }

    /**
     * @dataProvider raw_metadata_provider
     *
     * Test that raw metadata is correctly populated into instance.
     */
    public function test_populate_from_raw($variable, $rawkey, $rawvalue, $variableexists) {

        $rawdata = [$rawkey => $rawvalue];
        $resourcehash = sha1(random_string());

        $metadata = new \metadataextractor_mock\metadata(0, $resourcehash, $rawdata);

        // We're testing a private method, so we need to setup reflector magic.
        $method = new ReflectionMethod('\tool_metadata\metadata', 'populate_from_raw');
        $method->setAccessible(true); // Allow accessing of private method.
        $method->invoke($metadata, $resourcehash, $rawdata);

        if ($variableexists) {
            $this->assertObjectHasAttribute($variable, $metadata);
            $this->assertSame($rawvalue, $metadata->$variable);
        } else {
            $this->assertObjectNotHasAttribute($variable, $metadata);
        }
    }

    /**
     * Test populating metadata instance from an ID number of a metadata record.
     */
    public function test_populate_from_id() {
        global $DB;

        // Emulate metadataextractor returned raw metadata.
        $rawdata = [];
        $rawdata['meta:creator'] = 'Moodle';
        $rawdata['meta:title'] = 'Test title';

        // Emulate resourcehash from resource content.
        $resourcehash = sha1(random_string());

        // Insert existing record for testing metadata construction from existing record.
        $id = $DB->insert_record(metadataextractor_mock\metadata::TABLE,
            ['author' => $rawdata['meta:creator'], 'title' => $rawdata['meta:title'], 'resourcehash' => $resourcehash]);

        // Emulate metadataextractor returned raw metadata.
        $updatedrawdata = [];
        $updatedrawdata['meta:creator'] = 'Moodle 2.0';
        $updatedrawdata['meta:title'] = 'Updated title';

        // Create a new metadata instance populated with values differing to test record.
        $metadata = new \metadataextractor_mock\metadata(0, $resourcehash, $updatedrawdata);

        // We're testing a private method, so we need to setup reflector magic.
        $method = new ReflectionMethod('\tool_metadata\metadata', 'populate_from_id');
        $method->setAccessible(true); // Allow accessing of private method.
        $method->invoke($metadata, $id);

        // Populating from ID should override existing values with record values.
        $this->assertEquals($id, $metadata->get('id'));
        $this->assertEquals($rawdata['meta:creator'], $metadata->get('author'));
        $this->assertEquals($rawdata['meta:title'], $metadata->get('title'));

        $updatedrawdata['id'] = $id + 999; // ID which shouldn't exist yet
        $this->expectException(\tool_metadata\metadata_exception::class);
        $method->invoke($metadata, $updatedrawdata['id']);
    }

    /**
     * Test populating metadata instance from an ID number of a metadata record.
     */
    public function test_populate_from_resourcehash() {
        global $DB;

        // Emulate metadataextractor returned raw metadata.
        $rawdata = [];
        $rawdata['meta:creator'] = 'Moodle';
        $rawdata['meta:title'] = 'Test title';

        // Emulate resourcehash from resource content.
        $resourcehash = sha1(random_string());

        // Insert existing record for testing metadata construction from existing record.
        $id = $DB->insert_record(metadataextractor_mock\metadata::TABLE,
            ['author' => $rawdata['meta:creator'], 'title' => $rawdata['meta:title'], 'resourcehash' => $resourcehash]);

        // Emulate metadataextractor returned raw metadata.
        $updatedrawdata = [];
        $updatedrawdata['meta:creator'] = 'Moodle 2.0';
        $updatedrawdata['meta:title'] = 'Updated title';

        // Create a new metadata instance populated with values differing to test record.
        $metadata = new \metadataextractor_mock\metadata(0, $resourcehash, $updatedrawdata);

        // We're testing a private method, so we need to setup reflector magic.
        $method = new ReflectionMethod('\tool_metadata\metadata', 'populate_from_resourcehash');
        $method->setAccessible(true); // Allow accessing of private method.
        $method->invoke($metadata, $resourcehash);

        // Populating from resourcehash should override existing values with record values.
        $this->assertEquals($id, $metadata->get('id'));
        $this->assertEquals($rawdata['meta:creator'], $metadata->get('author'));
        $this->assertEquals($rawdata['meta:title'], $metadata->get('title'));

        $updatedrawdata['resourcehash'] = $id + 999; // ID which shouldn't exist yet
        $this->expectException(\tool_metadata\metadata_exception::class);
        $method->invoke($metadata, $updatedrawdata['resourcehash']);
    }

    /**
     * @dataProvider raw_metadata_provider
     *
     * Test get_associative_array.
     */
    public function test_get_associative_array($variable, $rawkey, $rawvalue, $variableexists) {
        $rawdata = [$rawkey => $rawvalue];
        $contenthash = sha1(random_string());
        $metadata = new metadataextractor_mock\metadata(0, $contenthash, $rawdata);
        $actual = $metadata->get_associative_array();

        if ($variableexists) {
            $this->assertArrayHasKey($variable, $actual);
            $this->assertEquals($rawvalue, $actual[$variable]);
        } else {
            $this->assertArrayNotHasKey($variable, $actual);
        }
    }
}
