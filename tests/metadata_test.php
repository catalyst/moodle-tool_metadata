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

/**
 * tool_metadata metadata tests.
 *
 * @package    tool_metadata
 * @copyright  2020 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      tool_metadata
 */
class tool_metadata_metadata_testcase extends advanced_testcase {

    /**
     * Date provider of mock raw metadata.
     *
     * @return array [
     *      string $attribute the name of the metadata attribute in mock_metadataextractor_metadata
     *      string $rawkey the key received in extracting metadata
     *      string $rawvalue the value received in extracting metadata
     *      bool $attributeexists Does mock_metadataextractor_metadata class have this attribute?
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
     * Test get_attribute_key_mapped_to.
     */
    public function test_get_attribute_key_mapped_to($attribute, $rawkey, $rawvalue, $attributeexists) {
        $rawdata = [$rawkey => $rawvalue];
        $contenthash = sha1(random_string());
        $metadata = new \metadataextractor_mock\metadata($contenthash, $rawdata, true);
        $actual = $metadata->get_attribute_key_mapped_to($rawkey);

        if ($attributeexists) {
            $this->assertEquals($attribute, $actual);
        } else {
            $this->assertEmpty($actual);
        }
    }

    /**
     * @dataProvider raw_metadata_provider
     *
     * Test is_key_mapped.
     */
    public function test_is_key_mapped($attribute, $rawkey, $rawvalue, $attributeexists) {
        $rawdata = [$rawkey => $rawvalue];
        $contenthash = sha1(random_string());
        $metadata = new \metadataextractor_mock\metadata($contenthash, $rawdata, true);
        $actual = $metadata->is_key_mapped($rawkey);

        $this->assertEquals($attributeexists, $actual);
    }

    /**
     * @dataProvider raw_metadata_provider
     *
     * Test that raw metadata is correctly mapped into instance.
     */
    public function test_populate_from_raw_metadata($attribute, $rawkey, $rawvalue, $attributeexists) {

        $rawdata = [$rawkey => $rawvalue];
        $contenthash = sha1(random_string());
        $metadata = new \metadataextractor_mock\metadata($contenthash, $rawdata, true);

        if ($attributeexists) {
            $this->assertObjectHasAttribute($attribute, $metadata);
            $this->assertSame($rawvalue, $metadata->$attribute);
        } else {
            $this->assertObjectNotHasAttribute($attribute, $metadata);
        }
    }

    /**
     * @dataProvider raw_metadata_provider
     *
     * Test get_associative_array.
     */
    public function test_get_associative_array($attribute, $rawkey, $rawvalue, $attributeexists) {
        $rawdata = [$rawkey => $rawvalue];
        $contenthash = sha1(random_string());
        $metadata = new metadataextractor_mock\metadata($contenthash, $rawdata, true);
        $actual = $metadata->get_associative_array();

        if ($attributeexists) {
            $this->assertArrayHasKey($attribute, $actual);
            $this->assertEquals($rawvalue, $actual[$attribute]);
        } else {
            $this->assertArrayNotHasKey($attribute, $actual);
        }
    }

    /**
     * @dataProvider raw_metadata_provider
     *
     * Test triggering creation of metadata.
     */
    public function test_triggercreation($attribute, $rawkey, $rawvalue, $attributeexists) {
        $rawdata = [$rawkey => $rawvalue];
        $contenthash = sha1(random_string());
        $metadata = new \metadataextractor_mock\metadata($contenthash, $rawdata, true);

        if ($attributeexists) {
            $this->assertObjectHasAttribute($attribute, $metadata);
            $this->assertEquals($rawvalue, $metadata->$attribute);
        } else {
            $this->assertObjectNotHasAttribute($attribute, $metadata);
        }

        $rawdata = [$attribute => $rawvalue];
        $rawdata['id'] = 1;
        $contenthash = sha1(random_string());
        $metadata = new \metadataextractor_mock\metadata($contenthash, $rawdata, false);

        if ($attributeexists) {
            $this->assertObjectHasAttribute($attribute, $metadata);
            $this->assertEquals($rawvalue, $metadata->$attribute);
        } else {
            $this->assertObjectNotHasAttribute($attribute, $metadata);
        }
    }
}
