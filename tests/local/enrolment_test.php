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

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the rule that says when an enrolment started.
 *
 * @package    certificateelement_enrolstart
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(enrolment::class)]
final class enrolment_test extends \advanced_testcase {
    /** @var int The time every test judges the enrolments at. */
    private const NOW = 1800000000;

    /**
     * Enrol a user through one method of a course, with the dates a test needs.
     *
     * @param int $courseid The course.
     * @param int $userid The user.
     * @param string $method The enrolment plugin name.
     * @param int $timestart The start date the method records, 0 for none.
     * @param int $timecreated When the enrolment row was created.
     * @param array $options Optional 'status' and 'timeend' of the enrolment, 'methodstatus' of the method.
     * @return void
     */
    private function enrol(
        int $courseid,
        int $userid,
        string $method,
        int $timestart,
        int $timecreated,
        array $options = []
    ): void {
        global $DB;

        $plugin = enrol_get_plugin($method);
        $instance = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => $method]);
        if (!$instance) {
            $id = $plugin->add_instance(get_course($courseid));
            $instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
        }
        $DB->set_field('enrol', 'status', $options['methodstatus'] ?? ENROL_INSTANCE_ENABLED, ['id' => $instance->id]);
        $plugin->enrol_user(
            $instance,
            $userid,
            null,
            $timestart,
            $options['timeend'] ?? 0,
            $options['status'] ?? ENROL_USER_ACTIVE
        );
        $DB->set_field(
            'user_enrolments',
            'timecreated',
            $timecreated,
            ['enrolid' => $instance->id, 'userid' => $userid]
        );
    }

    /**
     * A course and a user, as ids.
     *
     * @return array The course id, then the user id.
     */
    private function course_and_user(): array {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        return [(int) $course->id, (int) $user->id];
    }

    /**
     * The start date a method records is the start, whenever the row was created.
     */
    public function test_a_recorded_start_date_is_the_start(): void {
        [$courseid, $userid] = $this->course_and_user();
        $this->enrol($courseid, $userid, 'self', 1700000000, 1750000000);

        $this->assertSame(1700000000, enrolment::start($courseid, $userid, self::NOW));
    }

    /**
     * A method that records no start date (cohort sync, a course creator) starts when the row was created.
     */
    public function test_the_creation_time_stands_in_for_a_missing_start_date(): void {
        [$courseid, $userid] = $this->course_and_user();
        $this->enrol($courseid, $userid, 'manual', 0, 1750000000);

        $this->assertSame(1750000000, enrolment::start($courseid, $userid, self::NOW));
    }

    /**
     * With several methods the earliest start wins, each row read by its own rule.
     */
    public function test_the_earliest_enrolment_in_force_wins(): void {
        [$courseid, $userid] = $this->course_and_user();
        $this->enrol($courseid, $userid, 'manual', 0, 1750000000);
        $this->enrol($courseid, $userid, 'self', 1720000000, 1760000000);

        $this->assertSame(1720000000, enrolment::start($courseid, $userid, self::NOW));
    }

    /**
     * A suspended enrolment (a pending application is one) never counts, however early.
     */
    public function test_a_suspended_enrolment_never_counts(): void {
        [$courseid, $userid] = $this->course_and_user();
        $this->enrol($courseid, $userid, 'self', 0, 1700000000, ['status' => ENROL_USER_SUSPENDED]);

        // Alone, it gives no date at all.
        $this->assertSame(0, enrolment::start($courseid, $userid, self::NOW));

        $this->enrol($courseid, $userid, 'manual', 0, 1750000000);
        $this->assertSame(1750000000, enrolment::start($courseid, $userid, self::NOW));
    }

    /**
     * An enrolment on a disabled method yields to one in force, and is the answer when alone.
     */
    public function test_a_disabled_method_yields_to_an_enrolment_in_force(): void {
        [$courseid, $userid] = $this->course_and_user();
        $this->enrol($courseid, $userid, 'self', 1700000000, 1700000000, ['methodstatus' => ENROL_INSTANCE_DISABLED]);

        $this->assertSame(1700000000, enrolment::start($courseid, $userid, self::NOW));

        $this->enrol($courseid, $userid, 'manual', 0, 1750000000);
        $this->assertSame(1750000000, enrolment::start($courseid, $userid, self::NOW));
    }

    /**
     * An enrolment whose period ran out yields to one in force, and is the answer when alone.
     */
    public function test_an_expired_enrolment_yields_to_an_enrolment_in_force(): void {
        [$courseid, $userid] = $this->course_and_user();
        $this->enrol($courseid, $userid, 'self', 1700000000, 1700000000, ['timeend' => self::NOW - DAYSECS]);

        $this->assertSame(1700000000, enrolment::start($courseid, $userid, self::NOW));

        $this->enrol($courseid, $userid, 'manual', 0, 1750000000);
        $this->assertSame(1750000000, enrolment::start($courseid, $userid, self::NOW));
    }

    /**
     * An enrolment that has not started yet is the answer when alone, and later than one already running.
     */
    public function test_a_future_enrolment_is_later_than_one_already_running(): void {
        [$courseid, $userid] = $this->course_and_user();
        $this->enrol($courseid, $userid, 'self', self::NOW + DAYSECS, 1700000000);

        $this->assertSame(self::NOW + DAYSECS, enrolment::start($courseid, $userid, self::NOW));

        $this->enrol($courseid, $userid, 'manual', 0, self::NOW - DAYSECS);
        $this->assertSame(self::NOW - DAYSECS, enrolment::start($courseid, $userid, self::NOW));
    }

    /**
     * Only the asked user's enrolments in the asked course are read.
     */
    public function test_other_courses_and_other_users_are_not_read(): void {
        [$courseid, $userid] = $this->course_and_user();
        $othercourse = $this->getDataGenerator()->create_course();
        $otheruser = $this->getDataGenerator()->create_user();
        $this->enrol((int) $othercourse->id, $userid, 'manual', 1700000000, 1700000000);
        $this->enrol($courseid, (int) $otheruser->id, 'manual', 1710000000, 1710000000);

        $this->assertSame(0, enrolment::start($courseid, $userid, self::NOW));
        // The control: the same rows answer for the course and the user they belong to.
        $this->assertSame(1700000000, enrolment::start((int) $othercourse->id, $userid, self::NOW));
        $this->assertSame(1710000000, enrolment::start($courseid, (int) $otheruser->id, self::NOW));
    }
}
