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
 * tool_metadata extraction tests.
 *
 * @package    tool_metadata
 * @copyright  2020 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/admin/tool/metadata/constants.php');
require_once($CFG->dirroot . '/admin/tool/metadata/tests/mock_metadataextractor_extractor.php');

/**
 * tool_metadata extraction tests.
 *
 * @package    tool_metadata
 * @copyright  2020 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      tool_metadata
 */
class tool_metadata_extraction_testcase extends advanced_testcase {

    public function setUp(): void {
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

    public function tearDown(): void {
        global $DB;

        $dbman = $DB->get_manager();
        $table = new \xmldb_table(\metadataextractor_mock\metadata::TABLE);
        $dbman->drop_table($table);
    }

    public function test_get_highest_completed_resourceid() {
        $extractor = new \metadataextractor_mock\extractor();
        $mockresources = [];

        for ($i = 0; $i < 10; $i++) {
            // Create a test file resource.
            $fs = get_file_storage();
            $syscontext = context_system::instance();
            $filerecord = array(
                'author'    => 'Rick Sanchez',
                'contextid' => $syscontext->id,
                'component' => 'tool_metadata',
                'filearea'  => 'unittest',
                'itemid'    => $i,
                'filepath'  => '/',
                'filename'  => "test_$i.doc",
            );

            $file = $fs->create_file_from_string($filerecord, "This is test file #$i");
            $mockresources[$file->get_id()] = $file;
            $extraction = new \tool_metadata\extraction($file, TOOL_METADATA_RESOURCE_TYPE_FILE, $extractor);
            $extraction->set('status', \tool_metadata\extraction::STATUS_COMPLETE);
            $extraction->save();
        }

        asort($mockresources);
        $resourceids = array_keys($mockresources);
        $expected = end($resourceids);
        $actual = \tool_metadata\extraction::get_highest_completed_resourceid($extractor->get_name(),
            TOOL_METADATA_RESOURCE_TYPE_FILE);
        $this->assertEquals($expected, $actual);
    }
}
