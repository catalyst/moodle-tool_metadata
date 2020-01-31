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
 * tool_metadata api tests.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/admin/tool/metadata/constants.php');
require_once($CFG->dirroot . '/admin/tool/metadata/tests/mock_metadataextractor_metadata.php');
require_once($CFG->dirroot . '/admin/tool/metadata/tests/mock_metadataextractor_extractor.php');

/**
 * tool_metadata api tests.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      tool_metadata
 */
class tool_metadata_api_testcase extends advanced_testcase {

    public function setUp() {
        global $DB;

        $this->resetAfterTest();

        // Create a table for mock metadataextractor subplugin.
        $dbman = $DB->get_manager();
        $table = new \xmldb_table(\metadataextractor_mock\extractor::METADATA_TABLE);
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

    /**
     * Test correct creation of metadata records.
     */
    public function test_create_metadata() {
        global $DB;

        // Create a test document file.
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
        $docfile = $fs->create_file_from_string($filerecord, 'test doc');


        // Create a test image file from fixture.
        $filepath = __DIR__.'/fixtures/testimage.jpg';
        $syscontext = context_system::instance();
        $filerecord = array(
            'contextid' => $syscontext->id,
            'component' => 'tool_metadata',
            'filearea'  => 'unittest',
            'itemid'    => 0,
            'filepath'  => '/images/',
            'filename'  => 'testimage.jpg',
        );
        $imgfile = $fs->create_file_from_pathname($filerecord, $filepath);

        $extractor = new \metadataextractor_mock\extractor();
        $docextractedmetadata = \tool_metadata\api::extract_metadata($docfile, TOOL_METADATA_RESOURCE_TYPE_FILE, $extractor);
        $imgextractedmetadata = \tool_metadata\api::extract_metadata($imgfile, TOOL_METADATA_RESOURCE_TYPE_FILE, $extractor);

        // Create the metadata.
        $metadata = \tool_metadata\api::create_metadata($docextractedmetadata, $extractor);
        $record = $DB->get_record($extractor->get_table(), ['id' => $metadata->id]);
        $countone = $DB->count_records($extractor->get_table());

        // Expect all values to be stored in database without immutably.
        foreach (get_object_vars($metadata) as $attribute => $expected) {
            $this->assertEquals($expected, $record->$attribute);
        }

        // Attempt to create the same metadata again.
        \tool_metadata\api::create_metadata($docextractedmetadata, $extractor);
        $counttwo = $DB->count_records($extractor->get_table());

        // Expect that adding the same metadata should not create a new record.
        $this->assertEquals($countone, $counttwo);

        // Create metadata for another resource.
        $metadata = \tool_metadata\api::create_metadata($imgextractedmetadata, $extractor);
        $countthree = $DB->count_records($extractor->get_table());

        // Expect that adding metadata for another resource will create a new record.
        $this->assertGreaterThan($counttwo, $countthree);
    }
}
