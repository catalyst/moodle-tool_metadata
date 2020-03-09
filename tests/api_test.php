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

        $dbman->create_table($table);
    }

    public function tearDown() {
        global $DB;

        $dbman = $DB->get_manager();
        $table = new \xmldb_table(\metadataextractor_mock\metadata::TABLE);
        $dbman->drop_table($table);
    }

    public function test_get_supported_resource_types() {
        $actual = \tool_metadata\api::get_supported_resource_types();

        $this->assertContains(TOOL_METADATA_RESOURCE_TYPE_FILE, $actual);
        $this->assertContains(TOOL_METADATA_RESOURCE_TYPE_URL, $actual);
    }

    public function test_get_extractor() {
        $actual = \tool_metadata\api::get_extractor('mock');

        $this->assertInstanceOf(\metadataextractor_mock\extractor::class, $actual);
    }

    public function test_get_extraction() {

        // Create a test file resource.
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

        $extractor = \tool_metadata\api::get_extractor('mock');

        $actual = \tool_metadata\api::get_extraction($file, TOOL_METADATA_RESOURCE_TYPE_FILE, $extractor);

        $this->assertInstanceOf(\tool_metadata\extraction::class, $actual);
    }

    public function test_extract_metadata() {

        // Create a test file resource.
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
        $extractor = \tool_metadata\api::get_extractor('mock');

        $actual = \tool_metadata\api::extract_metadata($file, TOOL_METADATA_RESOURCE_TYPE_FILE, $extractor);

        $this->assertInstanceOf(\metadataextractor_mock\metadata::class, $actual);
    }

    public function test_async_metadata_extraction() {
        global $DB;

        // Create a test file resource.
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
        $extractor = \tool_metadata\api::get_extractor('mock');

        $extraction = \tool_metadata\api::async_metadata_extraction($file, TOOL_METADATA_RESOURCE_TYPE_FILE, $extractor);

        $this->assertInstanceOf(tool_metadata\extraction::class, $extraction);
        $this->assertEquals(tool_metadata\extraction::STATUS_ACCEPTED, $extraction->get('status'));

        // Should create an adhoc task for metadata extraction.
        $like = $DB->sql_like('classname', ':classname');
        $params = ['classname' => '%metadata_extraction_task'];
        $task = $DB->get_record_select('task_adhoc', $like, $params);
        $customparams = json_decode($task->customdata);
        $this->assertEquals($file->get_id(), $customparams->resourceid);
        $this->assertEquals(TOOL_METADATA_RESOURCE_TYPE_FILE, $customparams->type);
        $this->assertEquals($extractor->get_name(), $customparams->plugin);
    }

}
