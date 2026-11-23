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
 * Tests of the date frozen into a certificate issue.
 *
 * @package    certificateelement_enrolstart
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(snapshot::class)]
#[CoversClass(\certificateelement_enrolstart\observer::class)]
final class snapshot_test extends \advanced_testcase {
    /**
     * Issue a certificate of a new template, as the course certificate activity does.
     *
     * @param int $userid Who receives it.
     * @param int|null $courseid The course it is issued for, null for an issue outside a course.
     * @param array $data What the issuer stores with the issue.
     * @return \stdClass The row of tool_certificate_issues, read after the issue was made.
     */
    private function issue(int $userid, ?int $courseid, array $data = []): \stdClass {
        /** @var \tool_certificate_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certificate');
        $template = $generator->create_template((object) ['name' => 'Certificate']);
        $generator->create_page($template);
        $this->redirectMessages();
        return $generator->issue($template, $userid, null, $data, 'mod_coursecertificate', $courseid);
    }

    /**
     * Set the start date of a user's manual enrolment in a course.
     *
     * @param int $courseid The course.
     * @param int $userid The user.
     * @param int $timestart The new start date.
     * @return void
     */
    private function move_start(int $courseid, int $userid, int $timestart): void {
        global $DB;
        $enrolid = $DB->get_field('enrol', 'id', ['courseid' => $courseid, 'enrol' => 'manual'], MUST_EXIST);
        $DB->set_field('user_enrolments', 'timestart', $timestart, ['enrolid' => $enrolid, 'userid' => $userid]);
    }

    /**
     * Issuing a certificate for a course writes the start date beside what the issuer stored.
     */
    public function test_an_issue_for_a_course_freezes_the_start_date(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student', 'manual', 1700000000);

        $issue = $this->issue((int) $user->id, (int) $course->id, ['courseshortname' => 'C1']);

        $data = json_decode($issue->data, true);
        $this->assertSame(1700000000, $data[snapshot::KEY]);
        $this->assertSame(1700000000, snapshot::read($issue));
        // What the issuer and the certificate tool stored is still there.
        $this->assertSame('C1', $data['courseshortname']);
        $this->assertArrayHasKey('userfullname', $data);
    }

    /**
     * An issue made outside a course carries no date.
     */
    public function test_an_issue_outside_a_course_freezes_nothing(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $issue = $this->issue((int) $user->id, null);

        $this->assertArrayNotHasKey(snapshot::KEY, json_decode($issue->data, true));
        $this->assertNull(snapshot::read($issue));
        $this->assertSame(0, snapshot::start_of($issue));
    }

    /**
     * An enrolment changed after the issue changes nothing; without the frozen date it would.
     */
    public function test_the_frozen_date_outlives_a_change_to_the_enrolment(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student', 'manual', 1700000000);
        $issue = $this->issue((int) $user->id, (int) $course->id);

        $this->move_start((int) $course->id, (int) $user->id, 1710000000);

        $this->assertSame(1700000000, snapshot::start_of($issue));

        // The control: an issue with no frozen date reads the enrolment as it stands now.
        $data = json_decode($issue->data, true);
        unset($data[snapshot::KEY]);
        $issue->data = json_encode($data);
        $this->assertSame(1710000000, snapshot::start_of($issue));
    }

    /**
     * The enrolments are judged at the time of issue, not at the time the observer or a reader runs.
     */
    public function test_the_enrolments_are_judged_at_the_time_of_issue(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        // In force when the certificate is issued (2025), expired long before this test runs.
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student', 'manual', 1700000000, 1760000000);
        $self = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'self']);
        if (!$self) {
            $selfid = enrol_get_plugin('self')->add_instance($course);
            $self = $DB->get_record('enrol', ['id' => $selfid], '*', MUST_EXIST);
        }
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_ENABLED, ['id' => $self->id]);
        enrol_get_plugin('self')->enrol_user($self, $user->id, null, 1720000000);

        // The precondition: judged today, only the later enrolment is in force.
        $this->assertSame(1720000000, enrolment::start((int) $course->id, (int) $user->id, time()));

        $this->mock_clock_with_frozen(1750000000);
        $issue = $this->issue((int) $user->id, (int) $course->id);

        $this->assertSame(1750000000, (int) $issue->timecreated);
        $this->assertSame(1700000000, snapshot::read($issue));
    }

    /**
     * Regenerating the certificate keeps the frozen date.
     */
    public function test_a_regeneration_keeps_the_frozen_date(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student', 'manual', 1700000000);
        $issue = $this->issue((int) $user->id, (int) $course->id);
        $this->move_start((int) $course->id, (int) $user->id, 1710000000);

        \tool_certificate\template::instance($issue->templateid)->create_issue_file($issue, true);

        $stored = $DB->get_record('tool_certificate_issues', ['id' => $issue->id], '*', MUST_EXIST);
        $this->assertSame(1700000000, snapshot::read($stored));
    }

    /**
     * No enrolment at the time of issue is frozen too, so a later enrolment does not date the certificate.
     */
    public function test_no_enrolment_at_the_time_of_issue_is_frozen_as_none(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $issue = $this->issue((int) $user->id, (int) $course->id);

        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student', 'manual', 1700000000);

        $this->assertSame(0, snapshot::read($issue));
        $this->assertSame(0, snapshot::start_of($issue));
        // The control: the enrolment made afterwards is one the rule would read.
        $this->assertSame(1700000000, enrolment::start((int) $course->id, (int) $user->id, (int) $issue->timecreated));
    }
}
