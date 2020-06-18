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
 * Upgrade database for metadata api.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/admin/tool/metadata/db/upgradelib.php');

/**
 * Upgrade the plugin.
 *
 * @param int $oldversion
 * @return bool true on successful upgrade.
 */
function xmldb_tool_metadata_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2020021101) {
        $table = new xmldb_table('metadata_extractions');

        // Define field resourceid to be added to metadata_extractions.
        // Default the value to zero due to not null constraint, handled in extraction class.
        $field = new xmldb_field('resourceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0, 'id');

        // Conditionally launch add field resourceid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        remove_extractions_without_resourceid($oldversion);

        // Rename field contenthash on table metadata_extractions to resourcehash.
        $field = new xmldb_field('contenthash', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null, 'id');

        // Conditionally launch rename field contenthash to resourcehash.
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'resourcehash');
        }

        // Metadata savepoint reached.
        upgrade_plugin_savepoint(true, 2020021101, 'tool', 'metadata');
    }

    if ($oldversion < 2020040103) {

        // Changing name of table to meet Moodle plugin naming requirements.
        $table = new xmldb_table('metadata_extractions');
        $dbman->rename_table($table, 'tool_metadata_extractions');

        // Metadata savepoint reached.
        upgrade_plugin_savepoint(true, 2020040103, 'tool', 'metadata');
    }

    if ($oldversion < 2020040201) {

        // Changing type of field reason on table tool_metadata_extractions to text.
        $table = new xmldb_table('tool_metadata_extractions');
        $field = new xmldb_field('reason', XMLDB_TYPE_TEXT, null, null, null, null, null, 'status');

        // Launch change of type for field reason.
        $dbman->change_field_type($table, $field);

        // Metadata savepoint reached.
        upgrade_plugin_savepoint(true, 2020040201, 'tool', 'metadata');
    }

    if ($oldversion < 2020061800) {

        // Update mdl_config_plugins for tool_metadata plugin
        $sql = "SELECT *
          FROM {config_plugins}
         WHERE plugin = 'tool_metadata'
           AND name like '%startid'";
        $records = $DB->get_records_sql($sql);

        if ($records) {
            foreach ($records as $id => $record) {
                set_config($record->name, 0, 'tool_metadata');
            }
        }

        $table = new xmldb_table('tool_metadata_extractions');

        // Reset the tool_metadata_extractions table
        if ($dbman->table_exists($table)) {
            $DB->delete_records('tool_metadata_extractions');
        }

        $table = new xmldb_table('metadataextractor_tika');

        // Reset the metadataextractor_tika table
        if ($dbman->table_exists($table)) {
            $DB->delete_records('metadataextractor_tika');
        }

        $table = new xmldb_table('tika_audio_metadata');

        // Reset the tika_audio_metadata table
        if ($dbman->table_exists($table)) {
            $DB->delete_records('tika_audio_metadata');
        }

        $table = new xmldb_table('tika_document_metadata');

        // Reset the tika_document_metadata table
        if ($dbman->table_exists($table)) {
            $DB->delete_records('tika_document_metadata');
        }

        $table = new xmldb_table('tika_image_metadata');

        // Reset the tika_image_metadata table
        if ($dbman->table_exists($table)) {
            $DB->delete_records('tika_image_metadata');
        }

        $table = new xmldb_table('tika_pdf_metadata');

        // Reset the tika_pdf_metadata table
        if ($dbman->table_exists($table)) {
            $DB->delete_records('tika_pdf_metadata');
        }

        $table = new xmldb_table('tika_presentation_metadata');

        // Reset the tika_presentation_metadata table
        if ($dbman->table_exists($table)) {
            $DB->delete_records('tika_presentation_metadata');
        }

        $table = new xmldb_table('tika_spreadsheet_metadata');

        // Reset the tika_spreadsheet_metadata table
        if ($dbman->table_exists($table)) {
            $DB->delete_records('tika_spreadsheet_metadata');
        }

        $table = new xmldb_table('tika_video_metadata');

        // Reset the tika_video_metadata table
        if ($dbman->table_exists($table)) {
            $DB->delete_records('tika_video_metadata');
        }

        // Metadata savepoint reached.
        upgrade_plugin_savepoint(true, 2020061800, 'tool', 'metadata');
    }

    return true;
}