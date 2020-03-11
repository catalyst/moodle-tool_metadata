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
 * Mock metadataextractor subplugin extractor.
 *
 * @package    tool_metadata
 * @copyright  2020 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      tool_metadata
 */
namespace metadataextractor_mocktwo;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/admin/tool/metadata/tests/mock_metadataextractor_metadata.php');

/**
 * Mock metadataextractor subplugin extractor.
 *
 * @package    tool_metadata
 * @copyright  2020 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      tool_metadata
 */
class extractor extends \tool_metadata\extractor {

    /**
     * The plugin name.
     */
    const METADATAEXTRACTOR_NAME = 'mocktwo';

    /**
     * Table name for storing extracted metadata for this extractor.
     */
    const METADATA_BASE_TABLE = 'mock_metadata';

    /**
     * Mock method of metadata extraction from a file.
     *
     * @param \stored_file $file the file to extract metadata from.
     *
     * @return \tool_metadata\metadata a mock of metadata instance.
     */
    public function extract_file_metadata($file) {
        $rawmetadata = [];
        // Cheat by mocking metadata using the file's own contents.
        $rawmetadata['dc:creator'] = $file->get_author();
        $rawmetadata['dc:title'] = $file->get_filename();

        $metadata = new metadata(0, $file->get_contenthash(), $rawmetadata);

        return $metadata;
    }

    /**
     * Mock method of metadata extraction from a url.
     *
     * @param object $url the url instance to extract metadata from.
     */
    public function extract_url_metadata($url) {
        $rawmetadata = [];
        // Cheat by mocking metadata using the url's own contents.
        $rawmetadata['dc:creator'] = null;
        $rawmetadata['dc:title'] = $url->name;
    }
}