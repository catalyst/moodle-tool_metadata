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

        remove_extractions_without_resourceid();

        // Rename field contenthash on table metadata_extractions to resourcehash.
        $field = new xmldb_field('contenthash', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null, 'id');

        // Conditionally launch rename field contenthash to resourcehash.
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'resourcehash');
        }

        // Metadata savepoint reached.
        upgrade_plugin_savepoint(true, 2020021101, 'tool', 'metadata');
    }

    return true;
}