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

namespace certificateelement_enrolstart\privacy;

use certificateelement_enrolstart\local\snapshot;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the privacy provider.
 *
 * @package    certificateelement_enrolstart
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
    /** @var string The component under test. */
    private const COMPONENT = 'certificateelement_enrolstart';

    /** @var int The course the users are enrolled in. */
    private int $courseid;

    /** @var int The first user, whose issue carries the copy. */
    private int $userid;

    /** @var int The second user, whose issue carries a copy too. */
    private int $otherid;

    /** @var int A user whose issue carries no copy. */
    private int $bareid;

    /** @var int Row id of the first user's issue. */
    private int $issueid;

    /** @var int Row id of the second user's issue. */
    private int $otherissueid;

    /** @var int Row id of the issue with no copy. */
    private int $bareissueid;

    /**
     * A course with two enrolled users whose certificates carry the start date, and a user whose issue carries none.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->courseid = (int) $this->getDataGenerator()->create_course()->id;
        $this->userid = (int) $this->getDataGenerator()->create_user()->id;
        $this->otherid = (int) $this->getDataGenerator()->create_user()->id;
        $this->bareid = (int) $this->getDataGenerator()->create_user()->id;
        $this->getDataGenerator()->enrol_user($this->userid, $this->courseid, 'student', 'manual', 1700000000);
        $this->getDataGenerator()->enrol_user($this->otherid, $this->courseid, 'student', 'manual', 1710000000);

        $this->issueid = $this->issue($this->userid, $this->courseid);
        $this->otherissueid = $this->issue($this->otherid, $this->courseid);
        // An issue outside a course freezes nothing, so it carries no key.
        $this->bareissueid = $this->issue($this->bareid, null);
    }

    /**
     * Issue a certificate of a new template.
     *
     * @param int $userid Who receives it.
     * @param int|null $courseid The course it is issued for, null for an issue outside a course.
     * @return int Row id in tool_certificate_issues.
     */
    private function issue(int $userid, ?int $courseid): int {
        /** @var \tool_certificate_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certificate');
        static $count = 0;
        $template = $generator->create_template((object) ['name' => 'Privacy certificate ' . ++$count]);
        $generator->create_page($template);
        $this->redirectMessages();
        $issue = $generator->issue($template, $userid, null, ['courseshortname' => 'C1'], 'mod_coursecertificate', $courseid);
        return (int) $issue->id;
    }

    /**
     * The decoded data column of an issue.
     *
     * @param int $issueid Row id in tool_certificate_issues.
     * @return array The data.
     */
    private function data_of(int $issueid): array {
        global $DB;
        return json_decode($DB->get_field('tool_certificate_issues', 'data', ['id' => $issueid], MUST_EXIST), true);
    }

    /**
     * An approved list holding the system context for a user.
     *
     * @param int $userid The user.
     * @return approved_contextlist The list.
     */
    private function approved_for(int $userid): approved_contextlist {
        return new approved_contextlist(\core_user::get_user($userid), self::COMPONENT, [\context_system::instance()->id]);
    }

    /**
     * The plugin counts as compliant, which plugin CI never asks: core's own sweep is outside its testsuite.
     */
    public function test_the_component_is_compliant(): void {
        $this->assertTrue((new \core_privacy\manager())->component_is_compliant(self::COMPONENT));
    }

    /**
     * The collection declares the issue table and its data column, and every string it names exists.
     */
    public function test_the_metadata_declares_the_issue_data(): void {
        $items = provider::get_metadata(new collection(self::COMPONENT))->get_collection();

        $this->assertCount(1, $items);
        $this->assertSame('tool_certificate_issues', $items[0]->get_name());
        $this->assertArrayHasKey('data', $items[0]->get_privacy_fields());
        $strings = array_merge([$items[0]->get_summary()], array_values($items[0]->get_privacy_fields()));
        foreach ($strings as $id) {
            $this->assertTrue(get_string_manager()->string_exists($id, self::COMPONENT), $id);
        }
    }

    /**
     * Only a user with an issue that carries the key has the system context.
     */
    public function test_the_context_is_found_only_for_an_issue_with_the_key(): void {
        // The control: the issue without the key exists.
        $this->assertArrayNotHasKey(snapshot::KEY, $this->data_of($this->bareissueid));

        $this->assertSame(
            [\context_system::instance()->id],
            array_map('intval', provider::get_contexts_for_userid($this->userid)->get_contextids())
        );
        $this->assertSame([], provider::get_contexts_for_userid($this->bareid)->get_contextids());
    }

    /**
     * The system context lists the users whose issue carries the key; any other context lists nobody.
     */
    public function test_the_users_in_a_context(): void {
        $system = new userlist(\context_system::instance(), self::COMPONENT);
        provider::get_users_in_context($system);
        $this->assertEqualsCanonicalizing([$this->userid, $this->otherid], array_map('intval', $system->get_userids()));

        $course = new userlist(\context_course::instance($this->courseid), self::COMPONENT);
        provider::get_users_in_context($course);
        $this->assertSame([], $course->get_userids());
    }

    /**
     * The export holds the code and the stored date of the subject, whoever runs the request.
     */
    public function test_the_export_holds_the_subjects_date(): void {
        global $DB;

        $this->setAdminUser();
        $code = $DB->get_field('tool_certificate_issues', 'code', ['id' => $this->issueid], MUST_EXIST);

        $this->export_context_data_for_user($this->userid, \context_system::instance(), self::COMPONENT);

        $exported = writer::with_context(\context_system::instance())
            ->get_data([get_string('pluginname', self::COMPONENT), $code]);
        $this->assertSame($code, $exported->code);
        $this->assertSame(transform::datetime(1700000000), $exported->enrolmentstart);
        // The control: the other user's copy exists and is not in this export.
        $other = $DB->get_record('tool_certificate_issues', ['id' => $this->otherissueid], '*', MUST_EXIST);
        $this->assertSame(1710000000, snapshot::read($other));
        $othercode = $DB->get_field('tool_certificate_issues', 'code', ['id' => $this->otherissueid], MUST_EXIST);
        $this->assertEmpty(
            writer::with_context(\context_system::instance())->get_data([get_string('pluginname', self::COMPONENT), $othercode])
        );
    }

    /**
     * A user with no active enrolment when the certificate was issued exports no date, not the epoch.
     */
    public function test_the_export_of_no_enrolment_is_null(): void {
        global $DB;

        $userid = (int) $this->getDataGenerator()->create_user()->id;
        $issueid = $this->issue($userid, $this->courseid);
        $code = $DB->get_field('tool_certificate_issues', 'code', ['id' => $issueid], MUST_EXIST);
        // The precondition: the issue carries the key, with zero.
        $this->assertSame(0, $this->data_of($issueid)[snapshot::KEY]);

        provider::export_user_data($this->approved_for($userid));

        $exported = writer::with_context(\context_system::instance())
            ->get_data([get_string('pluginname', self::COMPONENT), $code]);
        $this->assertSame($code, $exported->code);
        $this->assertNull($exported->enrolmentstart);
    }

    /**
     * An issue without the key writes nothing.
     */
    public function test_the_export_skips_an_issue_without_the_key(): void {
        global $DB;

        $this->setAdminUser();
        $code = $DB->get_field('tool_certificate_issues', 'code', ['id' => $this->bareissueid], MUST_EXIST);

        provider::export_user_data($this->approved_for($this->bareid));

        $this->assertEmpty(
            writer::with_context(\context_system::instance())->get_data([get_string('pluginname', self::COMPONENT), $code])
        );
        // The control: the same call exports the issue of a user who has the key.
        provider::export_user_data($this->approved_for($this->userid));
        $this->assertTrue(writer::with_context(\context_system::instance())->has_any_data());
    }

    /**
     * Deleting for a user removes the key from that user's issue only, and nothing else of the data.
     */
    public function test_delete_data_for_user_removes_only_the_key(): void {
        provider::delete_data_for_user($this->approved_for($this->userid));

        $data = $this->data_of($this->issueid);
        $this->assertArrayNotHasKey(snapshot::KEY, $data);
        $this->assertSame('C1', $data['courseshortname']);
        $this->assertArrayHasKey('userfullname', $data);
        $this->assertSame(1710000000, $this->data_of($this->otherissueid)[snapshot::KEY]);
    }

    /**
     * Deleting for everybody works in the system context only.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        provider::delete_data_for_all_users_in_context(\context_course::instance($this->courseid));
        // The control: a course context removes nothing.
        $this->assertArrayHasKey(snapshot::KEY, $this->data_of($this->issueid));
        $this->assertArrayHasKey(snapshot::KEY, $this->data_of($this->otherissueid));

        provider::delete_data_for_all_users_in_context(\context_system::instance());

        foreach ([$this->issueid, $this->otherissueid, $this->bareissueid] as $id) {
            $data = $this->data_of($id);
            $this->assertArrayNotHasKey(snapshot::KEY, $data);
            $this->assertArrayHasKey('userfullname', $data);
        }
        $this->assertSame('C1', $this->data_of($this->issueid)['courseshortname']);
    }

    /**
     * Deleting for a list of users removes the key of the listed users only.
     */
    public function test_delete_data_for_users(): void {
        $userlist = new approved_userlist(\context_system::instance(), self::COMPONENT, [$this->userid]);

        provider::delete_data_for_users($userlist);

        $this->assertArrayNotHasKey(snapshot::KEY, $this->data_of($this->issueid));
        $this->assertArrayHasKey(snapshot::KEY, $this->data_of($this->otherissueid));
    }

    /**
     * The raw data column of an issue.
     *
     * @param int $issueid Row id in tool_certificate_issues.
     * @return string The column as stored.
     */
    private function raw_of(int $issueid): string {
        global $DB;
        return (string) $DB->get_field('tool_certificate_issues', 'data', ['id' => $issueid], MUST_EXIST);
    }

    /**
     * An issue outside a course for a new user, with the data column written as given.
     *
     * @param array $data What to store.
     * @return array The user id and the issue's row id.
     */
    private function issue_with_data(array $data): array {
        global $DB;

        $userid = (int) $this->getDataGenerator()->create_user()->id;
        $issueid = $this->issue($userid, null);
        $DB->set_field('tool_certificate_issues', 'data', json_encode($data), ['id' => $issueid]);
        return [$userid, $issueid];
    }

    /**
     * An approved list holding only a course context for a user.
     *
     * @param int $userid The user.
     * @return approved_contextlist The list.
     */
    private function approved_course_for(int $userid): approved_contextlist {
        return new approved_contextlist(
            \core_user::get_user($userid),
            self::COMPONENT,
            [\context_course::instance($this->courseid)->id]
        );
    }

    /**
     * The path of an issue's export.
     *
     * @param int $issueid Row id in tool_certificate_issues.
     * @return array The writer sub context.
     */
    private function export_path(int $issueid): array {
        global $DB;
        return [get_string('pluginname', self::COMPONENT), $DB->get_field('tool_certificate_issues', 'code', ['id' => $issueid])];
    }

    /**
     * The plugin's name as a value, not a key, passes the coarse filter and is neither exported nor rewritten.
     */
    public function test_a_value_that_is_not_the_key_is_left_alone(): void {
        [$decoyid, $decoyissue] = $this->issue_with_data(['userfullname' => 'X', 'note' => self::COMPONENT]);
        $before = $this->raw_of($decoyissue);
        $context = \context_system::instance();

        // The precondition: the SQL filter does find the decoy, so only the decode guard stops it.
        $this->assertSame([$context->id], array_map('intval', provider::get_contexts_for_userid($decoyid)->get_contextids()));

        provider::export_user_data($this->approved_for($decoyid));
        $this->assertEmpty(writer::with_context($context)->get_data($this->export_path($decoyissue)));
        provider::delete_data_for_user($this->approved_for($decoyid));
        $this->assertSame($before, $this->raw_of($decoyissue));

        // The control: the same calls act on a real copy.
        provider::export_user_data($this->approved_for($this->userid));
        $this->assertNotEmpty(writer::with_context($context)->get_data($this->export_path($this->issueid)));
        provider::delete_data_for_user($this->approved_for($this->userid));
        $this->assertArrayNotHasKey(snapshot::KEY, $this->data_of($this->issueid));
    }

    /**
     * The underscores of the key are literal in the filter: a key that differs only there is no copy.
     */
    public function test_a_key_that_differs_only_at_the_underscore_is_not_found(): void {
        [$lookalikeid] = $this->issue_with_data([str_replace('_', 'X', snapshot::KEY) => 1700000000]);

        $this->assertSame([], provider::get_contexts_for_userid($lookalikeid)->get_contextids());
        // The control: the real key is found for another user.
        $this->assertNotSame([], provider::get_contexts_for_userid($this->userid)->get_contextids());
    }

    /**
     * Export and deletion act only when the approved list holds the system context.
     */
    public function test_a_list_without_the_system_context_does_nothing(): void {
        $context = \context_system::instance();

        provider::export_user_data($this->approved_course_for($this->userid));
        provider::delete_data_for_user($this->approved_course_for($this->userid));

        $this->assertEmpty(writer::with_context($context)->get_data($this->export_path($this->issueid)));
        $this->assertArrayHasKey(snapshot::KEY, $this->data_of($this->issueid));

        // The control: the same calls with the system context export and strip.
        provider::export_user_data($this->approved_for($this->userid));
        $this->assertNotEmpty(writer::with_context($context)->get_data($this->export_path($this->issueid)));
        provider::delete_data_for_user($this->approved_for($this->userid));
        $this->assertArrayNotHasKey(snapshot::KEY, $this->data_of($this->issueid));
    }

    /**
     * Deleting for a list of users acts on the system context only.
     */
    public function test_delete_data_for_users_ignores_other_contexts(): void {
        provider::delete_data_for_users(
            new approved_userlist(\context_course::instance($this->courseid), self::COMPONENT, [$this->userid])
        );
        $this->assertArrayHasKey(snapshot::KEY, $this->data_of($this->issueid));

        provider::delete_data_for_users(new approved_userlist(\context_system::instance(), self::COMPONENT, [$this->userid]));
        $this->assertArrayNotHasKey(snapshot::KEY, $this->data_of($this->issueid));
    }

    /**
     * An issue whose data held only the key ends as an empty JSON object, not as an empty array.
     */
    public function test_an_issue_with_only_the_key_ends_as_an_empty_object(): void {
        [$userid, $issueid] = $this->issue_with_data([snapshot::KEY => 1700000000]);

        provider::delete_data_for_user($this->approved_for($userid));

        $this->assertSame('{}', $this->raw_of($issueid));
    }

    /**
     * A copy that is not a number exports the issue with no date.
     */
    public function test_a_malformed_copy_exports_no_date(): void {
        [$userid, $issueid] = $this->issue_with_data([snapshot::KEY => ['1700000000']]);

        provider::export_user_data($this->approved_for($userid));

        $exported = writer::with_context(\context_system::instance())->get_data($this->export_path($issueid));
        // The control: the issue was exported.
        $this->assertNotEmpty($exported->code);
        $this->assertNull($exported->enrolmentstart);
    }

    /**
     * A request built from what the finders return, as core builds it, exports and removes the copy.
     */
    public function test_a_request_built_from_the_finders_reaches_the_copy(): void {
        $system = \context_system::instance();
        $contextids = provider::get_contexts_for_userid($this->userid)->get_contextids();
        $approved = new approved_contextlist(\core_user::get_user($this->userid), self::COMPONENT, $contextids);

        provider::export_user_data($approved);
        $this->assertNotEmpty(writer::with_context($system)->get_data($this->export_path($this->issueid)));
        provider::delete_data_for_user($approved);
        $this->assertArrayNotHasKey(snapshot::KEY, $this->data_of($this->issueid));
        // The control: the other user's copy is still there for the user list to find.
        $this->assertArrayHasKey(snapshot::KEY, $this->data_of($this->otherissueid));

        $userlist = new userlist($system, self::COMPONENT);
        provider::get_users_in_context($userlist);
        provider::delete_data_for_users(new approved_userlist($system, self::COMPONENT, $userlist->get_userids()));
        $this->assertArrayNotHasKey(snapshot::KEY, $this->data_of($this->otherissueid));
    }
}
