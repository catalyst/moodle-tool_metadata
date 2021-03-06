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
 * Class for mocking various files with test metadata.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_metadata;

defined('MOODLE_INTERNAL') || die();

/**
 * Class for mocking various files with test metadata.
 *
 * @package    tool_metadata
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mock_file_builder {

    /**
     * Mock a PDF for testing.
     *
     * @return array consisting of [
     *      array $metadata = key/value pairs of expected metadata,
     *      \stored_file $file = the stored_file instance of mocked PDF
     * ]
     */
    public static function mock_pdf() {
        global $CFG, $SITE;

        require_once($CFG->libdir . '/pdflib.php');

        $metadata = [
            'Title' => 'Test PDF',
            'Author' => 'Moodle' . $CFG->release,
            'Keywords' => 'Moodle, PDF',
            'Subject' => 'This has been generated by Moodle.',
            'Content' => 'Hello World!' // Partial content of fixture.
        ];

        // Create test PDF.
        $doc = new \pdf();
        $doc->SetTitle($metadata['Title']);
        $doc->SetAuthor($metadata['Author']);
        $doc->SetKeywords($metadata['Keywords']);
        $doc->SetSubject($metadata['Subject']);
        $doc->SetMargins(15, 30);

        $doc->setPrintHeader(true);
        $doc->setHeaderMargin(10);
        $doc->setHeaderData('pix/moodlelogo.png', 40, $SITE->fullname, $CFG->wwwroot);

        $doc->setPrintFooter(true);
        $doc->setFooterMargin(10);

        $doc->AddPage('P');
        $doc->Write(5, $metadata['Content']);

        $buffer = $doc->Output('test.pdf', 'S');

        $fs = get_file_storage();
        $syscontext = \context_system::instance();
        $filerecord = array(
            'contextid' => $syscontext->id,
            'component' => 'tool_metadata',
            'filearea'  => 'unittest',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => 'test.pdf',
        );
        $file = $fs->create_file_from_string($filerecord, $buffer);
        unset($pdf);

        return [$metadata, $file];
    }

    /**
     * Mock a Word document and return the file and it's expected metadata.
     *
     * @return array consisting of [
     *      array $metadata = key/value pairs of expected metadata,
     *      \stored_file $file = the stored_file instance of fixture
     * ]
     */
    public static function mock_document() {
        global $CFG;

        $metadata = [
            'Title' => 'Test Document',
            'Subject' => 'tool_metadata',
            'Author' => 'Moodle',
            'Manager' => 'Test Manager',
            'Company' => 'Test Company',
            'Category' => 'testing',
            'Keywords' => 'test, document',
            'Comments' => 'Test Comments',
            'Pages' => 2,
            'Paragraphs' => 53,
            'Lines' => 59,
            'Words' => 148,
            'Characters' => 808,
            'Characters (with spaces)' => 904,
            'Content' => 'Test Word Document' // Partial content of fixture.
        ];

        $fs = get_file_storage();
        $syscontext = \context_system::instance();
        $filerecord = array(
            'contextid' => $syscontext->id,
            'component' => 'tool_metadata',
            'filearea' => 'unittest',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'metadata_test.docx');
        $fileurl = $CFG->dirroot . '/admin/tool/metadata/tests/fixtures/metadata_test.docx';
        $file = $fs->create_file_from_pathname($filerecord, $fileurl);

        return [$metadata, $file];
    }

}