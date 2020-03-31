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
$string['error:extractorclassnotfound'] = 'No extractor class found for metadataextractor_{$a} subplugin.';
$string['error:invalidextractionfilters'] = 'Extraction filters could not be parsed, invalid JSON, pleased check plugin setting tool_metadata/extraction_filters.';
$string['error:metadataclassnotfound'] = 'No metadata class found for metadataextractor_{$a} subplugin.';
$string['error:metadata:cannotpopulate'] = 'Cannot populate metadata instance, require either record ID or resource hash and record object.';
$string['error:metadata:noid'] = 'Cannot read, update or delete metadata record with no ID.';
$string['error:metadata:recordalreadyexists'] = 'Cannot create metadata record, already exists.';
$string['error:metadata:tablenotexists'] = 'Cannot create metadata, table does not exist.';
$string['error:nometadatatoupdate'] = 'Cannot update metadata, no existing metadata found.';
$string['error:pluginnotenabled'] = 'Metadata extractor {$a} is not enabled or not installed.';
$string['error:unsupportedresourcetype'] = '{$a->name} does not support {$a->type} resources.';

// Status messages for extraction.
$string['status:extractionaccepted'] = 'Metadata extraction task queued.';
$string['status:extractionnotinitiated'] = 'Metadata extraction has not been initiated.';
$string['status:extractioncommenced'] = 'Metadata extraction started.';
$string['status:extractioncomplete'] = 'Metadata extraction successfully completed.';
$string['status:extractionnotsupported'] = 'Metadata extraction not supported for resource id: {$a->resourceid}, type: {$a->type} by \'metadataextractor_{$a->plugin}\'.';
$string['status:nometadata'] = 'Could not extract metadata for resource id: {$a->resourceid}, type: {$a->type}.';

// Settings strings.
$string['settings:manageextraction'] = 'Manage extraction';
$string['settings:manageextractors'] = 'Manage metadata extractor subplugins';
$string['settings:manage'] = 'Metadata settings';
$string['settings:maxextractionprocesses'] = 'Maximum extraction processes';
$string['settings:maxextractionprocesses_help'] = 'Maximum extraction processes for each resource type which can be added to queue for asynchronous extraction.';
$string['settings:extractor:manage'] = 'Manage metadata extractor plugins';
$string['settings:error:invalidjson'] = 'Setting must be valid JSON, (hint: ensure double quotes are used).';
$string['settings:extractionfilters'] = 'Extraction filters';
$string['settings:extractionfilters_help'] = "<p>A JSON string containing an array of filter objects describing parameters to exclude from metadata extraction. Resource types which are not supported and field names which are invalid will be ignored.</p>
<p>Each filter object in the JSON array must adhere to the following structure:</p>
<pre><code>{
    \"type\": \"file\", // The resource type, (\"file\" or \"url\")
    \"field\": \"component\", // The field/column name of the resource table to filter by
    \"value\": \"badges\" // The field value to exclude from extractions
}</code></pre>
";
$string['settings:supportedfileextensions'] = 'Supported file extensions';

// Subplugin strings.
$string['subplugintype_metadataextractor'] = 'Extractor';
$string['subplugintype_metadataextractor_plural'] = 'Extractors';

// Task related strings.
$string['task:processfiles'] = 'Process file extractions';
$string['task:processurls'] = 'Process url extractions';
$string['task:cleanupfilemetadata'] = 'Cleanup file metadata';
