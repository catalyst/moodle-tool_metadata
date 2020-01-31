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
        $this->resetAfterTest();
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

}
