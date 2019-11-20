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
 * The response object for Metadata API requests.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_metadata;

defined('MOODLE_INTERNAL') || die();

/**
 * The response object for Metadata API requests.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class response {

    /**
     * Extraction status: extraction successfully completed.
     */
    const EXTRACTION_STATUS_COMPLETE = 200;

    /**
     * Extraction status: extraction currently in progress.
     */
    const EXTRACTION_STATUS_PENDING = 201;

    /**
     * Extraction status: extraction task created, not yet in progress.
     */
    const EXTRACTION_STATUS_ACCEPTED = 202;

    /**
     * Extraction status: no extracted metadata could be found.
     */
    const EXTRACTION_STATUS_NOT_FOUND = 404;

    /**
     * Extraction status: extraction complete, resource media type is not supported.
     */
    const EXTRACTION_STATUS_NOT_SUPPORTED = 415;

    /**
     * Extraction status: extraction completed with error.
     */
    const EXTRACTION_STATUS_ERROR = 500;

    /**
     * @var string SHA1 hash of the content of the data for this response.
     */
    public $contenthash;

    /**
     * @var int the status code of metadata extraction.
     */
    public $status;

    /**
     * @var string reason for status.
     */
    public $reason;

    /**
     * @var string the Moodle component name of the metadata extractor.
     */
    public $plugin;

    /**
     *
     * @var \tool_metadata\metadata the metadata object.
     */
    public $data;
}