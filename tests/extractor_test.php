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
 * tool_metadata extractor tests.
 *
 * @package    tool_metadata
 * @copyright  2020 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_metadata\helper;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/admin/tool/metadata/constants.php');
require_once($CFG->dirroot . '/admin/tool/metadata/tests/mock_metadataextractor_metadata.php');
require_once($CFG->dirroot . '/admin/tool/metadata/tests/mock_metadataextractor_extractor.php');
require_once($CFG->dirroot . '/admin/tool/metadata/tests/mock_metadataextractor_extractor_two.php');

/**
 * tool_metadata extractor tests.
 *
 * @package    tool_metadata
 * @copyright  2020 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      tool_metadata
 */
class tool_metadata_extractor_testcase extends advanced_testcase {

    public function setUp() {
        global $DB;

        $this->resetAfterTest();

        // Create a table for mock metadataextractor subplugin.
        $dbman = $DB->get_manager();
        $table = new \xmldb_table(\metadataextractor_mock\extractor::METADATA_BASE_TABLE);
        // Add mandatory fields.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('resourcehash', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null, 'id');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'date');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timecreated');
        // Add the fields used in mock class.
        $table->add_field('author', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'subject');
        $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'description');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        $dbman->create_table($table);
    }

    public function tearDown() {
        global $DB;

        $dbman = $DB->get_manager();
        $table = new \xmldb_table(\metadataextractor_mock\metadata::TABLE);
        $dbman->drop_table($table);
    }

    /**
     * Test getting the base table of extractor.
     */
    public function test_get_base_table() {
        $extractor = new \metadataextractor_mock\extractor();

        // Expect that the base table of an extractor is that instances base table.
        $this->assertEquals(\metadataextractor_mock\extractor::METADATA_BASE_TABLE, $extractor->get_base_table());
        $this->assertNotEquals(\tool_metadata\extractor::METADATA_BASE_TABLE, $extractor->get_base_table());
    }

    /**
     * Test getting the name of extractor.
     */
    public function test_get_name() {
        $extractor = new \metadataextractor_mock\extractor();

        // Expect that the name of an extractor is that instances name.
        $this->assertEquals(\metadataextractor_mock\extractor::METADATAEXTRACTOR_NAME, $extractor->get_name());
        $this->assertNotEquals(\tool_metadata\extractor::METADATAEXTRACTOR_NAME, $extractor->get_name());
    }

    /**
     * Test ability to extract metadata in extending classes.
     */
    public function test_extract_metadata() {
        $extractor = new \metadataextractor_mock\extractor();

        // Create a test file.
        $fs = get_file_storage();
        $syscontext = context_system::instance();
        $filerecord = array(
            'author'    => 'Rick Sanchez',
            'contextid' => $syscontext->id,
            'component' => 'tool_metadata',
            'filearea'  => 'unittest',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => 'test.doc',
        );
        $file = $fs->create_file_from_string($filerecord, 'test doc');

        $course = $this->getDataGenerator()->create_course();
        $url = $this->getDataGenerator()->create_module('url', ['course' => $course]);

        $actual = $extractor->extract_metadata($file, TOOL_METADATA_RESOURCE_TYPE_FILE);
        // Expect metadata object instance to be returned.
        $this->assertInstanceOf(\tool_metadata\metadata::class, $actual);

        // Expect an exception when trying to extract metadata for an unsupported resource type.
        $this->expectException(\tool_metadata\extraction_exception::class);
        $extractor->extract_metadata($url, TOOL_METADATA_RESOURCE_TYPE_URL);
    }

    /**
     * Test getting a list of extracted resourcehashes for extractor.
     */
    public function test_get_extracted_resourcehashes() {
        global $DB;

        $metadataone = new stdClass();
        $metadataone->resourcehash = sha1(random_string());
        $metadatatwo = new stdClass();
        $metadatatwo->resourcehash = sha1(random_string());

        // Create test metadata records in database.
        $DB->insert_records(\metadataextractor_mock\extractor::METADATA_BASE_TABLE, [$metadataone, $metadatatwo]);

        $extractor = new \metadataextractor_mock\extractor();
        $actual = $extractor->get_extracted_resourcehashes();

        $this->assertIsArray($actual);
        $this->assertContains($metadataone->resourcehash, $actual);
        $this->assertContains($metadatatwo->resourcehash, $actual);
    }

    /**
     * Test ability to get supported resource types from extending classes.
     */
    public function test_get_supported_resource_types() {
        $extractor = new \metadataextractor_mock\extractor();

        $this->assertTrue(in_array(TOOL_METADATA_RESOURCE_TYPE_FILE, $extractor->get_supported_resource_types()));
        $this->assertFalse(in_array(TOOL_METADATA_RESOURCE_TYPE_URL, $extractor->get_supported_resource_types()));

        $extractor = new \metadataextractor_mocktwo\extractor();
        $this->assertTrue(in_array(TOOL_METADATA_RESOURCE_TYPE_FILE, $extractor->get_supported_resource_types()));
        $this->assertTrue(in_array(TOOL_METADATA_RESOURCE_TYPE_URL, $extractor->get_supported_resource_types()));

    }

    /**
     * Test ability to check if an extending class supports a resource type.
     */
    public function test_supports_resource_type() {
        $extractor = new \metadataextractor_mock\extractor();

        $this->assertTrue($extractor->supports_resource_type(TOOL_METADATA_RESOURCE_TYPE_FILE));
        $this->assertFalse($extractor->supports_resource_type(TOOL_METADATA_RESOURCE_TYPE_URL));

        $extractor = new \metadataextractor_mocktwo\extractor();

        $this->assertTrue($extractor->supports_resource_type(TOOL_METADATA_RESOURCE_TYPE_FILE));
        $this->assertTrue($extractor->supports_resource_type(TOOL_METADATA_RESOURCE_TYPE_URL));
    }

    /**
     * Test validation of resource support by extractor.
     */
    public function test_validate_resource() {
        $extractor = new \metadataextractor_mock\extractor();
        [$unused, $file] = \tool_metadata\mock_file_builder::mock_pdf();

        $course = $this->getDataGenerator()->create_course();
        $url = $this->getDataGenerator()->create_module('url', ['course' => $course]);

        $this->assertTrue($extractor->validate_resource($file, TOOL_METADATA_RESOURCE_TYPE_FILE));
        $this->assertFalse($extractor->validate_resource($url, TOOL_METADATA_RESOURCE_TYPE_URL));
    }

    /**
     * Test checking if extractor has extracted metadata for a resource.
     */
    public function test_has_metadata() {

        // Create a test file.
        $fs = get_file_storage();
        $syscontext = context_system::instance();
        $filerecord = array(
            'author'    => 'Rick Sanchez',
            'contextid' => $syscontext->id,
            'component' => 'tool_metadata',
            'filearea'  => 'unittest',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => 'test.doc',
        );
        $file = $fs->create_file_from_string($filerecord, 'test doc');

        $extractor = new \metadataextractor_mock\extractor();

        $metadata = $extractor->extract_metadata($file, TOOL_METADATA_RESOURCE_TYPE_FILE);
        $metadata->save();
        $resourcehash = $metadata->get_resourcehash();
        $this->assertTrue($extractor->has_metadata($resourcehash));

        $resourcehash = sha1(random_string());
        $this->assertFalse($extractor->has_metadata($resourcehash));
    }

    /**
     * Test getting metadata associated with a resourcehash.
     */
    public function test_get_metadata() {

        // Create a test file.
        $fs = get_file_storage();
        $syscontext = context_system::instance();
        $filerecord = array(
            'author'    => 'Rick Sanchez',
            'contextid' => $syscontext->id,
            'component' => 'tool_metadata',
            'filearea'  => 'unittest',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => 'test.doc',
        );
        $file = $fs->create_file_from_string($filerecord, 'test doc');
        $resourcehash = helper::get_resourcehash($file, TOOL_METADATA_RESOURCE_TYPE_FILE);

        $extractor = new \metadataextractor_mock\extractor();
        $actual = $extractor->get_metadata($resourcehash);

        $this->assertNull($actual);

        $metadata = $extractor->extract_metadata($file, TOOL_METADATA_RESOURCE_TYPE_FILE);
        $metadata->save();

        $actual = $extractor->get_metadata($resourcehash);

        $this->assertEquals($metadata, $actual);
    }
}
