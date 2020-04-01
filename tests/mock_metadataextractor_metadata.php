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
 */

namespace metadataextractor_mock;

defined('MOODLE_INTERNAL') || die();

/**
 * Mock metadataextractor subplugin metadata class.
 *
 * @package    tool_metadata
 * @copyright  2020 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class metadata extends \tool_metadata\metadata {

    /**
     * @var string mock author.
     */
    public $author;

    /**
     * @var string mock title.
     */
    public $title;

    /**
     * Required: string - the table name where instance metadata is stored.
     */
    public const TABLE = 'mock_metadata';

    /**
     * Return the mapping of instantiating class variables to potential raw metadata keys
     * in order of priority from highest to lowest.
     *
     * @return array
     */
    protected function metadata_key_map() {
        return [
            'author' => ['Author', 'meta:author', 'Creator', 'meta:creator', 'dc:creator'],
            'title' => ['Title', 'meta:title', 'dc:title']
        ];
    }

}