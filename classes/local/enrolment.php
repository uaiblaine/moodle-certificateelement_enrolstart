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

/**
 * When a user's enrolment in a course started, whatever the enrolment method.
 *
 * @package    certificateelement_enrolstart
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class enrolment {
    /**
     * When one user enrolment started.
     *
     * Only some methods fill the start date: self enrolment, the manual enrolment screen, a
     * payment and an approved application do, while cohort sync, an external database, LDAP and
     * the enrolment of whoever creates a course leave it at zero, which core documents as
     * "unknown". The time the row was created stands in for it then, as it does in core's own
     * duration criterion ({@see \completion_criteria_duration::cron()}). A restore keeps the
     * start date and creates the row anew, so an enrolment restored without a start date reads
     * as started at the restore.
     *
     * @param int $timestart The row's start date, 0 when the method set none.
     * @param int $timecreated When the row was created.
     * @return int Unix time, 0 when neither is known.
     */
    public static function effective_start(int $timestart, int $timecreated): int {
        if ($timestart > 0) {
            return $timestart;
        }
        return max($timecreated, 0);
    }

    /**
     * When the user's enrolment in the course started.
     *
     * A user can hold one enrolment per method. The answer is the earliest start among the
     * enrolments in force at the given time: an active enrolment, on an enabled method, not past
     * its end date. With none in force (the method was disabled or the period ran out before the
     * certificate was issued) it is the earliest among the active ones. A start date still to
     * come needs no rule of its own: it is later than any enrolment already running. A suspended
     * enrolment never counts: a pending application is one, and its row was created when the
     * person applied, not when they were let in.
     *
     * @param int $courseid The course.
     * @param int $userid The user.
     * @param int $now The time the enrolments are judged at.
     * @return int Unix time, 0 when the user has no active enrolment in the course.
     */
    public static function start(int $courseid, int $userid, int $now): int {
        global $DB;

        $sql = "SELECT ue.id, ue.timestart, ue.timeend, ue.timecreated, e.status AS methodstatus
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE e.courseid = :courseid
                       AND ue.userid = :userid
                       AND ue.status = :active";
        $rows = $DB->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $userid, 'active' => ENROL_USER_ACTIVE]);

        $inforce = [];
        $active = [];
        foreach ($rows as $row) {
            $start = self::effective_start((int) $row->timestart, (int) $row->timecreated);
            if ($start === 0) {
                continue;
            }
            $active[] = $start;
            $timeend = (int) $row->timeend;
            if (
                (int) $row->methodstatus === ENROL_INSTANCE_ENABLED
                && ($timeend === 0 || $timeend > $now)
            ) {
                $inforce[] = $start;
            }
        }

        if ($inforce) {
            return min($inforce);
        }
        return $active ? min($active) : 0;
    }
}
