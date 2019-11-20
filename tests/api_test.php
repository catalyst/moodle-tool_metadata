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
        $this->resetAfterTest();
    }

    public function test_get_file_metadata_no_extractor() {

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
        $file = $fs->create_file_from_pathname($filerecord, $filepath);

        $response = \tool_metadata\api::get_file_metadata($file);
        // Set no enabled plugins.
        \tool_metadata\plugininfo\metadataextractor::set_enabled_plugins([]);

        // If there are no metadata extractors installed, response should contain error message.
        $this->assertEquals(\tool_metadata\response::EXTRACTION_STATUS_ERROR, $response->status);
        $this->assertEmpty($response->data);
    }


}