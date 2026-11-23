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

namespace certificateelement_enrolstart\local;

use stdClass;

/**
 * The enrolment start date frozen into a certificate issue.
 *
 * A certificate template lives in the system or a category context and knows no course; the
 * issue does. tool_certificate keeps what an issue was made from as JSON in
 * tool_certificate_issues.data, and every later rendering of the PDF reads it, so the date is
 * written there once, when the certificate is issued, and an enrolment changed or removed
 * afterwards changes no certificate.
 *
 * @package    certificateelement_enrolstart
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class snapshot {
    /** @var string Key of the frozen date in the issue's data; the plugin's name, so no issuer's own key collides. */
    public const KEY = 'certificateelement_enrolstart';

    /**
     * Freeze the enrolment start date into an issue.
     *
     * Every issue made for a course gets one, whether or not its template shows the date, so a
     * template that gains the element later prints the date of the day of issue when its
     * certificates are regenerated. Zero is written when the user has no active enrolment: the
     * key then says "looked, found none", and {@see self::start_of()} does not look again.
     *
     * @param int $issueid Row id in tool_certificate_issues.
     * @return void
     */
    public static function capture(int $issueid): void {
        global $DB;

        $issue = $DB->get_record('tool_certificate_issues', ['id' => $issueid], 'id, userid, courseid, timecreated, data');
        if (!$issue || empty($issue->courseid)) {
            return;
        }

        $data = json_decode((string) $issue->data, true);
        if (!is_array($data)) {
            $data = [];
        }
        $data[self::KEY] = enrolment::start((int) $issue->courseid, (int) $issue->userid, (int) $issue->timecreated);
        $DB->set_field('tool_certificate_issues', 'data', json_encode($data), ['id' => $issue->id]);
    }

    /**
     * The date frozen in an issue.
     *
     * @param stdClass|null $issue Row of tool_certificate_issues.
     * @return int|null Unix time, 0 for "no enrolment when it was issued", null when the issue carries no date.
     */
    public static function read(?stdClass $issue): ?int {
        if (!$issue || empty($issue->data)) {
            return null;
        }
        $data = json_decode((string) $issue->data, true);
        if (!is_array($data) || !array_key_exists(self::KEY, $data)) {
            return null;
        }
        return max((int) $data[self::KEY], 0);
    }

    /**
     * The enrolment start date an issue prints.
     *
     * The frozen date when the issue has one. An issue older than this plugin has none: its
     * enrolments are then read as they stand now, judged at the time of issue, which is the best
     * answer left and can change until the certificate is issued again.
     *
     * @param stdClass|null $issue Row of tool_certificate_issues.
     * @return int Unix time, 0 when there is nothing to print.
     */
    public static function start_of(?stdClass $issue): int {
        $frozen = self::read($issue);
        if ($frozen !== null) {
            return $frozen;
        }
        if (!$issue || empty($issue->courseid) || empty($issue->userid)) {
            return 0;
        }
        return enrolment::start((int) $issue->courseid, (int) $issue->userid, (int) ($issue->timecreated ?? 0));
    }
}
