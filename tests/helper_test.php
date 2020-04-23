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
        $file = $fs->create_file_from_pathname($filerecord, $filepath);

        $expected = $file->get_contenthash();
        $actual = \tool_metadata\helper::get_resourcehash($file, TOOL_METADATA_RESOURCE_TYPE_FILE);

        $this->assertSame($expected, $actual);
    }

    public function test_get_resourcehash_url() {
        $course = $this->getDataGenerator()->create_course();
        $url = $this->getDataGenerator()->create_module('url', ['course' => $course]);

        // Expect the resourcehash to be a hash of the full url with params, as the actual page data is dynamic
        // and may change whereas url will stay constant.
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
     * Test getting database fields for a resource.
     *
     * @dataProvider resource_fields_provider
     *
     * @param string $type resource type.
     * @param array $expected string[] expected fields.
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
        set_config('extraction_filters', 'Invalid JSON', 'tool_metadata');

        $this->expectException(\tool_metadata\extraction_exception::class);
        \tool_metadata\helper::get_resource_extraction_filters($type);
    }

    /**
     * Test getting a handle for a file resource.
     */
    public function test_get_resource_stream_file() {
        [$unused, $file] = \tool_metadata\mock_file_builder::mock_document();
        $expected = stream_get_contents($file->get_content_file_handle());

        $handle = \tool_metadata\helper::get_resource_stream($file, TOOL_METADATA_RESOURCE_TYPE_FILE);
        $this->assertInstanceOf(\Psr\Http\Message\StreamInterface::class, $handle);

        $actual = $handle->getContents();
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test getting a handle for a url resource.
     */
    public function test_get_resource_stream_url() {
        global $CFG;

        $course = $this->getDataGenerator()->create_course();
        $url = $this->getDataGenerator()->create_module('url', ['course' => $course]);
        $fixturepath = __DIR__ . '/fixtures/url_fixture.html';
        $url->externalurl = $CFG->wwwroot . $fixturepath;
        $url->parameters = 'a:0:{}'; // Remove any query params from generated url.
        $expected = file_get_contents($fixturepath);

        // Mock returning the fixture content, if we use a real URL it could have date data in the content which is mutable
        // and may change between GET requests.
        $mock = new \GuzzleHttp\Handler\MockHandler([
            // Test working connection and successful GET request.
            new \GuzzleHttp\Psr7\Response(200, [], $expected),
            // Test working connection but empty response content.
            new \GuzzleHttp\Psr7\Response(204, []),
            // Test a URL which is not accessible.
            new \GuzzleHttp\Exception\BadResponseException('Request denied',
                new \GuzzleHttp\Psr7\Request('GET', $url->externalurl),
                new \GuzzleHttp\Psr7\Response(401)),
            // Test a URL which returns a HTTP status code outside 100-600.
            new InvalidArgumentException('999 Request denied'),
            // Test network error when attempting to retrieve URL content.
            new \GuzzleHttp\Exception\ConnectException('Connection Error',
                new \GuzzleHttp\Psr7\Request('GET', $url->externalurl))
        ]);
        $handlerstack = \GuzzleHttp\HandlerStack::create($mock);
        $params = ['handler' => $handlerstack];

        // Handle should have the content of URL.
        $handle = \tool_metadata\helper::get_resource_stream($url, TOOL_METADATA_RESOURCE_TYPE_URL, $params);
        $this->assertInstanceOf(\Psr\Http\Message\StreamInterface::class, $handle);
        $actual = $handle->getContents();
        $this->assertEquals($expected, $actual);

        // If the URL has no content, the handle should be empty.
        $handle = \tool_metadata\helper::get_resource_stream($url, TOOL_METADATA_RESOURCE_TYPE_URL, $params);
        $this->assertEquals(0, $handle->getSize());
        $actual = $handle->getContents();
        $this->assertEmpty($actual);

        // If the content of the URL is not accessible, the handle should be null.
        $actual = \tool_metadata\helper::get_resource_stream($url, TOOL_METADATA_RESOURCE_TYPE_URL, $params);
        $this->assertNull($actual);

        // If the URL returns a status code outside of 100-600, the handle should be null as URL is unsupported.
        // Some servers will return a HTTP Status Code outside of the PSR7 accepted range of 100-600
        // which leads to an InvalidArgumentException being thrown, refer to https://github.com/guzzle/guzzle/issues/2534.
        $actual = \tool_metadata\helper::get_resource_stream($url, TOOL_METADATA_RESOURCE_TYPE_URL, $params);
        $this->assertNull($actual);

        // If there was a network issue when attempting to get the URL content, exception should be thrown.
        $this->expectException(\tool_metadata\network_exception::class);
        \tool_metadata\helper::get_resource_stream($url, TOOL_METADATA_RESOURCE_TYPE_URL, $params);
    }
}
