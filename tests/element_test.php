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

namespace certificateelement_enrolstart;

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/pdflib.php');

/**
 * Tests of the enrolment start date element.
 *
 * @package    certificateelement_enrolstart
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(element::class)]
final class element_test extends \advanced_testcase {
    /**
     * The certificate tool's generator.
     *
     * @return \tool_certificate_generator
     */
    private function generator(): \tool_certificate_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_certificate');
    }

    /**
     * A template with one page holding one enrolment start date element.
     *
     * @param array $data The element's form data.
     * @return array The template, then the element.
     */
    private function template_with_element(array $data = ['name' => 'Start']): array {
        $template = $this->generator()->create_template((object) ['name' => 'Certificate']);
        $pageid = $this->generator()->create_page($template)->get_id();
        /** @var element $element */
        $element = $this->generator()->create_element($pageid, 'enrolstart', $data);
        return [$template, $element];
    }

    /**
     * A PDF that expects one text to be written, or none.
     *
     * @param string|null $text The text expected, null when nothing may be written.
     * @return \pdf
     */
    private function pdf_expecting(?string $text): \pdf {
        $pdf = $this->getMockBuilder(\pdf::class)->onlyMethods(['writeHTMLCell'])->getMock();
        if ($text === null) {
            $pdf->expects($this->never())->method('writeHTMLCell');
        } else {
            $pdf->expects($this->once())
                ->method('writeHTMLCell')
                ->with($this->anything(), $this->anything(), $this->anything(), $this->anything(), $text);
        }
        $pdf->AddPage();
        return $pdf;
    }

    /**
     * The PDF of an issue prints the frozen start date in the format the element was saved with.
     */
    public function test_render_prints_the_start_date_in_the_chosen_format(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student', 'manual', 1700000000);
        [$template, $element] = $this->template_with_element(['name' => 'Start', 'dateformat' => 'strftimedaydate']);
        $this->redirectMessages();
        $issue = $this->generator()->issue($template, $user, null, [], 'mod_coursecertificate', (int) $course->id);

        $expected = userdate(1700000000, get_string('strftimedaydate', 'langconfig'));
        // The precondition: the chosen format is not the default one.
        $this->assertNotSame($expected, userdate(1700000000, get_string('strftimedate', 'langconfig')));

        $element->render($this->pdf_expecting($expected), false, $user, $issue);
    }

    /**
     * An issue with no enrolment behind it prints nothing.
     */
    public function test_render_prints_nothing_without_an_enrolment(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        [$template, $element] = $this->template_with_element();
        $this->redirectMessages();
        $issue = $this->generator()->issue($template, $user, null, [], 'mod_coursecertificate', (int) $course->id);

        $element->render($this->pdf_expecting(null), false, $user, $issue);
    }

    /**
     * The template preview and the editor show the current date.
     */
    public function test_the_preview_shows_the_current_date(): void {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(1720000000);
        $user = $this->getDataGenerator()->create_user();
        [, $element] = $this->template_with_element();

        $expected = userdate(1720000000, get_string('strftimedate', 'langconfig'));

        $element->render($this->pdf_expecting($expected), true, $user, null);
        $this->assertStringContainsString($expected, $element->render_html());
    }

    /**
     * The form reads back the format it saved, and a rename leaves it alone.
     */
    public function test_the_format_survives_the_form_and_a_partial_update(): void {
        $this->resetAfterTest();
        [, $element] = $this->template_with_element(['name' => 'Start', 'dateformat' => 'strftimemonthyear']);

        $this->assertSame('strftimemonthyear', $element->prepare_data_for_form()->dateformat);

        $element->save_form_data((object) ['name' => 'Renamed']);
        $reloaded = \tool_certificate\element::instance($element->get_id());
        $this->assertSame('Renamed', $reloaded->get_name());
        $this->assertSame('strftimemonthyear', $reloaded->get_date_format());
    }

    /**
     * A format the element does not offer reads as the default, wherever it came from.
     */
    public function test_an_unknown_format_reads_as_the_default(): void {
        $this->resetAfterTest();
        [, $element] = $this->template_with_element(['name' => 'Start', 'dateformat' => 'strftimetime']);

        $this->assertSame(element::DEFAULT_FORMAT, $element->get_date_format());
        $this->assertSame(
            userdate(1700000000, get_string('strftimedate', 'langconfig')),
            element::format_date(1700000000, 'strftimetime')
        );
    }

    /**
     * Every offered format writes the sample date its own way.
     */
    public function test_every_offered_format_is_distinct(): void {
        $formats = element::get_date_formats();

        $this->assertArrayHasKey(element::DEFAULT_FORMAT, $formats);
        $this->assertCount(count($formats), array_unique($formats));
        // The leading zero is the whole difference between the two short formats.
        $this->assertStringStartsWith('0', $formats['strftimedatefullshortwleadingzero']);
        $this->assertStringStartsNotWith('0', $formats['strftimedatefullshort']);
    }
}
