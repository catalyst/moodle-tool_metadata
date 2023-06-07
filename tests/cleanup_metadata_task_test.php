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
 * cleanup_metadata_task_test.
 *
 * @package    tool_metadata
 * @copyright  2020 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_metadata\extraction;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/admin/tool/metadata/constants.php');
require_once($CFG->dirroot . '/admin/tool/metadata/tests/mock_metadataextractor_extractor.php');
require_once($CFG->dirroot . '/admin/tool/metadata/tests/mock_metadataextractor_extractor_two.php');

/**
 * cleanup_metadata_task_test.
 *
 * @package    tool_metadata
 * @copyright  2020 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      tool_metadata
 */
class cleanup_metadata_task_test extends advanced_testcase {

    /**
     * @var string[] mock metadataextractor plugins.
     */
    protected $mockplugins;

    public function setUp(): void {
        global $DB;

        $this->resetAfterTest();

        // Create tables for mock metadataextractor subplugins.
        $dbman = $DB->get_manager();
        $this->mockplugins = ['mock', 'mocktwo'];
        foreach ($this->mockplugins as $plugin) {
            $table = new \xmldb_table('metadataextractor_' . $plugin);
            // Add mandatory fields.
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('resourcehash', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null, 'id');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'date');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timecreated');
            // Add the fields used in mock class.
            $table->add_field('author', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'subject');
            $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'description');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

            $dbman->create_table($table);

            // Insert version records for the mock metadataextractor plugin, otherwise it will not be seen as installed.
            $record = new stdClass();
            $record->plugin = 'metadataextractor_' . $plugin;
            $record->name = 'version';
            $record->value = time();

            $DB->insert_record('config_plugins', $record);
        }
        // Enable the mock plugins.
        \tool_metadata\plugininfo\metadataextractor::set_enabled_plugins($this->mockplugins);
    }

    public function tearDown(): void {
        global $DB;

        // Drop the mock metadataextractor tables to avoid any funny business.
        $dbman = $DB->get_manager();
        foreach ($this->mockplugins as $plugin) {
            $table = new \xmldb_table('metadataextractor_' . $plugin);
            $dbman->drop_table($table);
        }

        // Reset the adhoc task queue.
        $DB->delete_records('task_adhoc');
    }

    public function test_get_deleted_resourcehashes() {
        global $DB;

        // Delete all files so we have a known state.
        $DB->delete_records('files');
        $DB->delete_records('url');

        // Generate mock resources.
        [$unused, $docfile] = \tool_metadata\mock_file_builder::mock_document();
        [$unused, $pdffile] = \tool_metadata\mock_file_builder::mock_pdf();

        $course = $this->getDataGenerator()->create_course();
        $urlone = $this->getDataGenerator()->create_module('url',
            ['course' => $course, 'externalurl' => 'https://testsiteone.nonesuch.com']);
        $urltwo = $this->getDataGenerator()->create_module('url',
            ['course' => $course, 'externalurl' => 'https://testsitetwo.nonesuch.com']);

        $mockresources = [
            TOOL_METADATA_RESOURCE_TYPE_FILE => [$docfile, $pdffile],
            TOOL_METADATA_RESOURCE_TYPE_URL => [$urlone, $urltwo]
        ];

        // Extract metadata for our mocks using mock extractors.
        $extractor = new \metadataextractor_mock\extractor();
        $extractortwo = new \metadataextractor_mocktwo\extractor();
        $extractors = [$extractor, $extractortwo];

        foreach ($mockresources as $type => $resources) {
            foreach ($resources as $resource) {
                foreach ($extractors as $extractor) {
                    if ($extractor->supports_resource_type($type)) {
                        $extraction = new extraction($resource, $type, $extractor);
                        $extraction->set('status', extraction::STATUS_COMPLETE);
                        $extraction->save();

                        $metadata = \tool_metadata\api::extract_metadata($resource, $type, $extractor);
                        $metadata->save();
                    }
                }
            }
        }

        $task = new \tool_metadata\task\cleanup_metadata_task();

        // Should not be any results if nothing to clean up.
        $this->assertEmpty($task->get_deleted_resourcehashes($extractor));
        $this->assertEmpty($task->get_deleted_resourcehashes($extractortwo));

        // Delete some resource records from their tables.
        $docresourcehash = \tool_metadata\helper::get_resourcehash($docfile, TOOL_METADATA_RESOURCE_TYPE_FILE);
        $DB->delete_records(\tool_metadata\helper::get_resource_table(TOOL_METADATA_RESOURCE_TYPE_FILE),
            ['contenthash' => $docresourcehash]);

        $urlid = \tool_metadata\helper::get_resource_id($urlone, TOOL_METADATA_RESOURCE_TYPE_URL);
        $urlresourcehash = \tool_metadata\helper::get_resourcehash($urlone, TOOL_METADATA_RESOURCE_TYPE_URL);
        $DB->delete_records(\tool_metadata\helper::get_resource_table(TOOL_METADATA_RESOURCE_TYPE_URL),
            ['id' => $urlid]);

        // Should contain resourcehashes for deleted resources.
        $actual = $task->get_deleted_resourcehashes($extractor);
        $this->assertContains($docresourcehash, $actual);
        $this->assertContains($urlresourcehash, $actual);
    }

    public function test_execute() {
        global $DB;

        // Delete all files so we have a known state.
        $DB->delete_records('files');
        $DB->delete_records('url');

        // Generate mock resources.
        [$unused, $docfile] = \tool_metadata\mock_file_builder::mock_document();
        [$unused, $pdffile] = \tool_metadata\mock_file_builder::mock_pdf();

        $course = $this->getDataGenerator()->create_course();
        $urlone = $this->getDataGenerator()->create_module('url',
            ['course' => $course, 'externalurl' => 'https://testsiteone.nonesuch.com']);
        $urltwo = $this->getDataGenerator()->create_module('url',
            ['course' => $course, 'externalurl' => 'https://testsitetwo.nonesuch.com']);

        $mockresources = [
            TOOL_METADATA_RESOURCE_TYPE_FILE => [$docfile, $pdffile],
            TOOL_METADATA_RESOURCE_TYPE_URL => [$urlone, $urltwo]
        ];

        // Extract metadata for our mocks using mock extractors.
        $extractor = new \metadataextractor_mock\extractor();
        $extractortwo = new \metadataextractor_mocktwo\extractor();
        $extractors = [$extractor, $extractortwo];

        foreach ($mockresources as $type => $resources) {
            foreach ($resources as $resource) {
                foreach ($extractors as $extractor) {
                    if ($extractor->supports_resource_type($type)) {
                        $extraction = new extraction($resource, $type, $extractor);
                        $extraction->set('status', extraction::STATUS_COMPLETE);
                        $extraction->save();

                        $metadata = \tool_metadata\api::extract_metadata($resource, $type, $extractor);
                        $metadata->save();
                    }
                }
            }
        }

        $task = new \tool_metadata\task\cleanup_metadata_task();

        // We are expecting mtrace to output tool_metadata:... messages during task execution.
        $this->expectOutputRegex("/tool_metadata\:/");

        // No records should be deleted if no resources have been deleted.
        $task->execute();
        $extractionresourcehashes = $DB->get_fieldset_select('tool_metadata_extractions', 'resourcehash', '');
        $metadataresourcehashes = $DB->get_fieldset_select('tool_metadata_extractions', 'resourcehash', '');

        foreach ($mockresources as $type => $resources) {
            foreach ($resources as $resource) {
                foreach ($extractors as $extractor) {
                    if ($extractor->supports_resource_type($type)) {
                        $this->assertContains(\tool_metadata\helper::get_resourcehash($resource, $type), $extractionresourcehashes);
                        $this->assertContains(\tool_metadata\helper::get_resourcehash($resource, $type), $metadataresourcehashes);
                    }
                }
            }
        }

        // Delete some resource records from their tables.
        $docresourcehash = \tool_metadata\helper::get_resourcehash($docfile, TOOL_METADATA_RESOURCE_TYPE_FILE);
        $DB->delete_records(\tool_metadata\helper::get_resource_table(TOOL_METADATA_RESOURCE_TYPE_FILE),
            ['contenthash' => $docresourcehash]);

        $urlid = \tool_metadata\helper::get_resource_id($urlone, TOOL_METADATA_RESOURCE_TYPE_URL);
        $urlresourcehash = \tool_metadata\helper::get_resourcehash($urlone, TOOL_METADATA_RESOURCE_TYPE_URL);
        $DB->delete_records(\tool_metadata\helper::get_resource_table(TOOL_METADATA_RESOURCE_TYPE_URL),
            ['id' => $urlid]);

        // All metadata records and extraction records should be deleted for deleted resources.
        $task->execute();
        $extractionresourcehashes = $DB->get_fieldset_select('tool_metadata_extractions', 'resourcehash', '');
        $metadataresourcehashes = $DB->get_fieldset_select('tool_metadata_extractions', 'resourcehash', '');
        $this->assertNotContains($docresourcehash, $extractionresourcehashes);
        $this->assertNotContains($docresourcehash, $metadataresourcehashes);
        $this->assertNotContains($urlresourcehash, $extractionresourcehashes);
        $this->assertNotContains($urlresourcehash, $metadataresourcehashes);
    }
}
