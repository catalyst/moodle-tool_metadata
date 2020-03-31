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
 * tool_metadata helper tests.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/admin/tool/metadata/constants.php');
require_once($CFG->dirroot . '/mod/url/locallib.php');

/**
 * tool_metadata helper tests.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      tool_metadata
 */
class tool_metadata_helper_testcase extends advanced_testcase {

    public function setUp() {
        $this->resetAfterTest();
    }

    public function test_get_resourcehash_file() {

        // Create a test file from fixture.
        $fs = get_file_storage();
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
        $file = $fs->create_file_from_string($filerecord, 'Test file content');

        $expected = $file->get_contenthash();
        $actual = \tool_metadata\helper::get_resourcehash($file, TOOL_METADATA_RESOURCE_TYPE_FILE);

        $this->assertSame($expected, $actual);
    }

    public function test_get_resourcehash_url() {
        $course = $this->getDataGenerator()->create_course();
        $url = $this->getDataGenerator()->create_module('url', ['course' => $course]);

        // Expect the resourcehash to be a hash of the full url with params, as the actual page data is dynamic and may change whereas url
        // will stay constant.
        $cm = get_coursemodule_from_instance('url', $url->id, $url->course, false, MUST_EXIST);
        $fullurl = url_get_full_url($url, $cm, $url->course);
        $expected = sha1($fullurl);
        $actual = \tool_metadata\helper::get_resourcehash($url, TOOL_METADATA_RESOURCE_TYPE_URL);

        $this->assertEquals($expected, $actual);
    }

    /**
     * Resource database fields provider.
     *
     * @return array
     */
    public function resource_fields_provider() {
        return [
            [
                TOOL_METADATA_RESOURCE_TYPE_FILE,
                [
                    'id', 'contenthash', 'pathnamehash', 'contextid', 'component', 'filearea', 'itemid', 'filepath',
                    'filename', 'userid', 'filesize', 'mimetype', 'status', 'source', 'author', 'license',
                    'timecreated', 'timemodified', 'sortorder', 'referencefileid'

                ]
            ],
            [
                TOOL_METADATA_RESOURCE_TYPE_URL,
                [
                    'id', 'course', 'name', 'intro', 'introformat', 'externalurl', 'display', 'displayoptions',
                    'parameters', 'timemodified'
                ]
            ]
        ];
    }

    /**
     * @dataProvider resource_fields_provider
     *
     * @param $type string resource type.
     * @param $expected array string[] expected fields.
     */
    public function test_get_resource_fields($type, $expected) {
        $actual = \tool_metadata\helper::get_resource_fields($type);

        $this->assertEquals($expected, $actual);
    }

    public function test_get_resource_extraction_filters() {

        // Test a valid file extraction filter.
        $type = TOOL_METADATA_RESOURCE_TYPE_FILE;
        $field = 'component';
        $value = 'assignsubmission_file';

        set_config('extraction_filters', "[{\"type\": \"$type\", \"field\": \"$field\", \"value\": \"$value\"}]",
            'tool_metadata');

        $fields = \tool_metadata\helper::get_resource_extraction_filters($type);
        $actual = reset($fields);
        $this->assertEquals($type, $actual->type);
        $this->assertEquals($field, $actual->field);
        $this->assertEquals($value, $actual->value);

        // Test an invalid file extraction filter.
        $type = TOOL_METADATA_RESOURCE_TYPE_FILE;
        $field = 'fieldnotexists';
        $value = 'assignsubmission_file';

        set_config('extraction_filters', "[{\"type\": \"$type\", \"field\": \"$field\", \"value\": \"$value\"}]",
            'tool_metadata');

        // Invalid field name should exclude filter from results.
        $actual = \tool_metadata\helper::get_resource_extraction_filters($type);
        $this->assertEmpty($actual);

        // No setting should result in empty filters.
        unset_config('extraction_filters', 'tool_metadata');

        $actual = \tool_metadata\helper::get_resource_extraction_filters($type);
        $this->assertEmpty($actual);

        // Test a valid url extraction filter.
        $type = TOOL_METADATA_RESOURCE_TYPE_URL;
        $field = 'name';
        $value = 'Test link';

        set_config('extraction_filters', "[{\"type\": \"$type\", \"field\": \"$field\", \"value\": \"$value\"}]",
            'tool_metadata');

        $fields = \tool_metadata\helper::get_resource_extraction_filters($type);
        $actual = reset($fields);
        $this->assertEquals($type, $actual->type);
        $this->assertEquals($field, $actual->field);
        $this->assertEquals($value, $actual->value);

        // Test an invalid url extraction filter.
        $type = TOOL_METADATA_RESOURCE_TYPE_URL;
        $field = 'fieldnotexists';
        $value = 'Test link';

        set_config('extraction_filters', "[{\"type\": \"$type\", \"field\": \"$field\", \"value\": \"$value\"}]",
            'tool_metadata');

        // Invalid field name should exclude filter from results.
        $actual = \tool_metadata\helper::get_resource_extraction_filters($type);
        $this->assertEmpty($actual);

        // Invalid JSON setting should throw an exception.
        set_config('extraction_filters', 'Invalid JSON','tool_metadata');

        $this->expectException(\tool_metadata\extraction_exception::class);
        \tool_metadata\helper::get_resource_extraction_filters($type);
    }

}