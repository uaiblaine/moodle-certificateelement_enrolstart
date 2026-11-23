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

use certificateelement_enrolstart\local\snapshot;
use tool_certificate\element_helper;

/**
 * Certificate element printing the date the learner's enrolment in the course started.
 *
 * @package    certificateelement_enrolstart
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class element extends \tool_certificate\element {
    /** @var string The format a new element starts with, and the one an unknown stored format reads as. */
    public const DEFAULT_FORMAT = 'strftimedate';

    /** @var string The short date with a leading zero on the day, which no language pack names. */
    private const LEADING_ZERO = 'strftimedatefullshortwleadingzero';

    /**
     * The date formats offered, each shown as one fixed sample date.
     *
     * @return array Format name => the sample date in that format.
     */
    public static function get_date_formats(): array {
        // A fixed date whose day is a single digit, so the leading zero formats can be told apart.
        $sample = 1530849658;

        $formats = [];
        foreach (self::format_names() as $name) {
            $formats[$name] = self::format_date($sample, $name);
        }
        return $formats;
    }

    /**
     * The names of the date formats offered, in the order the form lists them.
     *
     * @return array List of format names.
     */
    private static function format_names(): array {
        return [
            'strftimedate',
            'strftimedatefullshort',
            self::LEADING_ZERO,
            'strftimedateshort',
            'strftimedaydate',
            'strftimedayshort',
            'strftimemonthyear',
            'strftimedatetime',
            'strftimedatetimeshort',
        ];
    }

    /**
     * A date written in one of the offered formats.
     *
     * @param int $time Unix time.
     * @param string $format A name from {@see self::get_date_formats()}; anything else reads as the default.
     * @return string
     */
    public static function format_date(int $time, string $format): string {
        if ($format === self::LEADING_ZERO) {
            return userdate($time, get_string('strftimedatefullshort', 'langconfig'), 99, false);
        }
        $pattern = match ($format) {
            'strftimedatefullshort' => get_string('strftimedatefullshort', 'langconfig'),
            'strftimedateshort' => get_string('strftimedateshort', 'langconfig'),
            'strftimedaydate' => get_string('strftimedaydate', 'langconfig'),
            'strftimedayshort' => get_string('strftimedayshort', 'langconfig'),
            'strftimemonthyear' => get_string('strftimemonthyear', 'langconfig'),
            'strftimedatetime' => get_string('strftimedatetime', 'langconfig'),
            'strftimedatetimeshort' => get_string('strftimedatetimeshort', 'langconfig'),
            default => get_string('strftimedate', 'langconfig'),
        };
        return userdate($time, $pattern);
    }

    /**
     * The date format this element was saved with.
     *
     * @return string A name from {@see self::get_date_formats()}.
     */
    public function get_date_format(): string {
        $stored = json_decode((string) $this->get_data(), true);
        $format = is_array($stored) ? ($stored['dateformat'] ?? '') : '';
        return in_array($format, self::format_names(), true) ? $format : self::DEFAULT_FORMAT;
    }

    /**
     * Add this element's fields to the element form.
     *
     * @param \MoodleQuickForm $mform The edit form.
     * @return void
     */
    public function render_form_elements($mform) {
        $mform->addElement('static', 'startrule', '', get_string('startrule', 'certificateelement_enrolstart'));

        $mform->addElement(
            'select',
            'dateformat',
            get_string('dateformat', 'certificateelement_enrolstart'),
            self::get_date_formats()
        );
        $mform->setDefault('dateformat', self::DEFAULT_FORMAT);
        $mform->addHelpButton('dateformat', 'dateformat', 'certificateelement_enrolstart');

        parent::render_form_elements($mform);
    }

    /**
     * The saved element as form data.
     *
     * @return \stdClass
     */
    public function prepare_data_for_form() {
        $record = parent::prepare_data_for_form();
        $record->dateformat = $this->get_date_format();
        return $record;
    }

    /**
     * Save what the element form submitted.
     *
     * @param \stdClass $data The form data, or the part of it being updated.
     * @return void
     */
    public function save_form_data(\stdClass $data) {
        // A partial update (a rename, a move) carries no format and must not reset the stored one.
        if (property_exists($data, 'dateformat')) {
            // Stored as given: get_date_format() is the one place that judges it.
            $data->data = json_encode(['dateformat' => $data->dateformat]);
        }
        parent::save_form_data($data);
    }

    /**
     * Print the element on the PDF.
     *
     * Nothing is printed for an issue with no enrolment behind it: a certificate issued outside
     * a course, or to somebody who was not enrolled.
     *
     * @param \pdf $pdf The PDF.
     * @param bool $preview Whether this is the template preview.
     * @param \stdClass $user The user the certificate is for.
     * @param \stdClass|null $issue The issue being rendered, null on a preview.
     * @return void
     */
    public function render($pdf, $preview, $user, $issue) {
        if ($preview) {
            $time = \core\di::get(\core\clock::class)->time();
        } else {
            $time = snapshot::start_of($issue ?: null);
        }
        if ($time > 0) {
            element_helper::render_content($pdf, $this, self::format_date($time, $this->get_date_format()));
        }
    }

    /**
     * The element as HTML, for the template editor's drag and drop page.
     *
     * @return string
     */
    public function render_html() {
        $now = \core\di::get(\core\clock::class)->time();
        return element_helper::render_html_content($this, self::format_date($now, $this->get_date_format()));
    }
}
