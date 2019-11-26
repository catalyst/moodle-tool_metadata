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
 * Strings for component 'tool_metadata', language 'en'
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Metadata';

// Error strings.
$string['error:extractionfailed'] = 'Metadata extraction failed.';
$string['error:pluginnotenabled'] = 'Metadata extractor {$a} is not enabled or not installed.';
$string['error:noenabledextractors'] = 'There are no enabled metadata extractors, you must install or enable one to extract metadata.';

// Status messages for extraction.
$string['status:extractionnotinitiated'] = 'Metadata extraction has not been initiated.';
$string['status:extractioncommenced'] = 'Metadata extraction started.';
$string['status:extractioncomplete'] = 'Metadata extraction successfully completed.';

// Help strings for metadata fields.
$string['fileid_help'] = 'The file id for the resource this metadata applies to.';
$string['creator_help'] = 'The person or organization primarily responsible for creating the intellectual content of the resource this metadata represents.';
$string['contributor_help'] = 'Persons or organizations not specified in the creator who have made significant intellectual contributions to the resource but whose contribution is secondary to any person or organization specified in creator.';
$string['creationdate_help'] = 'A UNIX epoch for date/time associated with the creation or availability of the resource.';
$string['description_help'] = 'A textual description of the content of the resource.';
$string['extractor_help'] = 'The pluginname of the metadata extractor plugin used to create this metadata record.';
$string['format_help'] = 'The MIME type of the resource, in accordance with IANA Media Types (https://www.iana.org/assignments/media-types/media-types.xhtml).';
$string['publisher_help'] = 'The entity responsible for making the resource available in its present form, such as a publishing house, a university department, or a corporate entity.';
$string['rights_help'] = 'A rights management statement, an identifier that links to a rights management statement, or an identifier that links to a service providing information about rights management for the resource.';
$string['subject_help'] = 'The topic of the resource.  Typically, subject will be expressed as keywords or phrases that describe the subject or content of the resource.';
$string['title_help'] = 'The name given to the resource, usually by the creator or publisher.';
$string['timecreated_help'] = 'UNIX epoch date/time that this record was created.';
$string['type_help'] = 'One of the available types from the DCMI Type Vocabulary (https://dublincore.org/specifications/dublin-core/dcmi-terms/2012-06-14/?v=dcmitype#section-7-dcmi-type-vocabulary).';

// Settings strings.
$string['settings:extractor:manage'] = 'Manage metadata extractor plugins';
$string['settings:supportedfileextensions'] = 'Supported file extensions';

// Subplugin strings.
$string['subplugintype_metadataextractor'] = 'Extractor';
$string['subplugintype_metadataextractor_plural'] = 'Extractors';



