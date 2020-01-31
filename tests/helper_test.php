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

}