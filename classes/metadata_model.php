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
 * Define the core metadata model for all resources.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_metadata;

defined('MOODLE_INTERNAL') || die();

/**
 * The core metadata model for all resources.
 *
 * This model follows a modified version of Dublin Core tailored for Moodle.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class metadata_model  {

    /**
     * Metadata type - An aggregation of resources.
     */
    const DCMI_TYPE_COLLECTION = 'collection';

    /**
     * Metadata type - Data encoded in a defined structure.
     */
    const DCMI_TYPE_DATASET = 'dataset';

    /**
     * Metadata type - A non-persistent, time-based occurrence.
     */
    const DCMI_TYPE_EVENT = 'event';

    /**
     * Metadata type - A resource requiring interaction from the user to be understood, executed, or experienced.
     */
    const DCMI_TYPE_INTERACTIVE = 'interactiveresource';

    /**
     * Metadata type - A visual representation other than text.
     */
    const DCMI_TYPE_IMAGE = 'image';

    /**
     * Metadata type - A series of visual representations imparting an impression of motion when shown in succession.
     */
    const DCMI_TYPE_MOVINGIMAGE = 'movingimage';

    /**
     * Metadata type - An inanimate, three-dimensional object or substance.
     */
    const DCMI_TYPE_PHYSICAL = 'physicalobject';

    /**
     * Metadata type - A system that provides one or more functions.
     */
    const DCMI_TYPE_SERVICE = 'service';

    /**
     * Metadata type - A computer program in source or compiled form.
     */
    const DCMI_TYPE_SOFTWARE = 'software';

    /**
     * Metadata type - A resource primarily intended to be heard.
     */
    const DCMI_TYPE_SOUND = 'sound';

    /**
     * Metadata type - A static visual representation.
     */
    const DCMI_TYPE_STILLIMAGE = 'stillimage';

    /**
     * Metadata type - A resource consisting primarily of words for reading.
     */
    const DCMI_TYPE_TEXT = 'text';

    /**
     * Moodle resource type - file.
     */
    const RESOURCE_TYPE_FILE = 'file';

    /**
     * Moodle resource type - URL link to an external resouce.
     */
    const RESOURCE_TYPE_URL = 'url';

    /**
     * @var string A uniqueid for the resource this metadata applies to, such as a filecontenthash for
     * a Moodle file, or the context id of an activity or resource.
     */
    public $uniqueid;

    /**
     * @var string The Moodle resource type, such as file, book, external URL, page, etc.
     */
    public $resourcetype;

    /**
     * @var string The person or organization primarily responsible for creating the
     * intellectual content of the resource this metadata represents.
     */
    public $creator;

    /**
     * @var array (string) Persons or organizations not specified in the creator who have
     * made significant intellectual contributions to the resource but whose contribution
     * is secondary to any person or organization specified in creator.
     */
    public $contributor;

    /**
     * @var int A UNIX epoch for date/time associated with the creation or availability of the resource.
     */
    public $creationdate;

    /**
     * @var string The MIME type of the resource, in accordance with IANA Media Types.
     * https://www.iana.org/assignments/media-types/media-types.xhtml
     */
    public $format;

    /**
     * @var string One of the Dublic Core Metadata Initiative types available in the DCMI_TYPE constants.
     * https://www.dublincore.org/specifications/dublin-core/dcmi-type-vocabulary/2010-10-11/
     */
    public $type;

    /**
     * @var string The name given to the resource, usually by the creator or publisher.
     */
    public $title;

    /**
     * @var string The topic of the resource.  Typically, subject will be expressed as
     * keywords or phrases that describe the subject or content of the resource.
     */
    public $subject;

    /**
     * @var string A textual description of the content of the resource.
     */
    public $description;

    /**
     * @var string The entity responsible for making the resource available in its
     * present form, such as a publishing house, a university department, or a corporate entity.
     */
    public $publisher;

    /**
     * @var string A rights management statement, an identifier that links to a rights management statement,
     * or an identifier that links to a service providing information about rights management for the resource.
     */
    public $rights;

    /**
     * @var int The UNIX timestamp of when instance was created.
     */
    public $timecreated;

    /**
     * @var int The UNIX timestamp of when instance was last modified.
     */
    public $timemodified;

    /**
     * @var string The classname of the extractor responsible for creating this instance.
     *
     * This allow for creation of various metadata records by different metadataextractor type plugins.
     */
    public $extractor;

}