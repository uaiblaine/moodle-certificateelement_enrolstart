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
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider: the enrolment start date copied into a certificate issue.
 *
 * The plugin has no table of its own, yet it writes personal data: the copy of the enrolment
 * start date lives under its own key in the data column of tool_certificate_issues, a row
 * of another plugin. That plugin's provider does not export the column, so a data request would
 * never hand the copy to the learner; this provider exports it and removes it. The provider of
 * tool_certificate deletes the whole issue on a deletion request, so the key removal here is what
 * keeps this provider complete on its own, whatever happens to the issue.
 *
 * @package    certificateelement_enrolstart
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the data the plugin writes into the certificate issue.
     *
     * @param collection $collection The collection to add to.
     * @return collection The collection with the issue table described.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'tool_certificate_issues',
            [
                'userid' => 'privacy:metadata:issues:userid',
                'data' => 'privacy:metadata:issues:data',
            ],
            'privacy:metadata:issues'
        );
        return $collection;
    }

    /**
     * The contexts that hold a copy of the user's enrolment start date: the system context, when one issue carries it.
     *
     * @param int $userid The user.
     * @return contextlist The contexts.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        [$select, $params] = self::carrying_select(true);
        $contextlist = new contextlist();
        if ($DB->record_exists_select('tool_certificate_issues', $select, $params + ['userid' => $userid])) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * The users with an issue that carries a copy, in the system context.
     *
     * @param userlist $userlist The list to add to.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }
        [$select, $params] = self::carrying_select(false);
        $userlist->add_from_sql('userid', "SELECT userid FROM {tool_certificate_issues} WHERE $select", $params);
    }

    /**
     * Export the copy of the enrolment start date in each of the user's issues that carries one.
     *
     * @param approved_contextlist $contextlist The approved contexts; the user is the one the request is about.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $context = \context_system::instance();
        if (!in_array((int) $context->id, array_map('intval', $contextlist->get_contextids()), true)) {
            return;
        }

        [$select, $params] = self::carrying_select(true);
        $params['userid'] = $contextlist->get_user()->id;
        $issues = $DB->get_recordset_select('tool_certificate_issues', $select, $params, 'timecreated, id');
        foreach ($issues as $issue) {
            $data = self::decode_carrying($issue->data);
            if ($data === null) {
                continue;
            }
            $start = is_scalar($data[snapshot::KEY]) ? (int) $data[snapshot::KEY] : 0;
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'certificateelement_enrolstart'), $issue->code],
                (object) [
                    'code' => $issue->code,
                    'timecreated' => transform::datetime((int) $issue->timecreated),
                    'enrolmentstart' => $start > 0 ? transform::datetime($start) : null,
                ]
            );
        }
        $issues->close();
    }

    /**
     * Remove the copy from every issue, in the system context.
     *
     * @param \context $context The context.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        if (!$context instanceof \context_system) {
            return;
        }
        [$select, $params] = self::carrying_select(false);
        self::strip_key($select, $params);
    }

    /**
     * Remove the copy from the user's issues.
     *
     * @param approved_contextlist $contextlist The approved contexts; the user is the one the request is about.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $systemid = (int) \context_system::instance()->id;
        if (!in_array($systemid, array_map('intval', $contextlist->get_contextids()), true)) {
            return;
        }
        [$select, $params] = self::carrying_select(true);
        self::strip_key($select, $params + ['userid' => $contextlist->get_user()->id]);
    }

    /**
     * Remove the copy from the issues of the listed users, in the system context.
     *
     * @param approved_userlist $userlist The users and the context.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        if (!$userlist->get_context() instanceof \context_system || !$userlist->get_userids()) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED, 'uid');
        [$select, $params] = self::carrying_select(false);
        self::strip_key("$select AND userid $insql", $params + $inparams);
    }

    /**
     * The condition that finds the issues whose data names the plugin's key.
     *
     * Only a coarse filter: a LIKE cannot tell the key from the same text elsewhere in the JSON,
     * so every row it returns is decoded again before it is read or rewritten.
     *
     * @param bool $peruser Whether the condition also takes the :userid placeholder.
     * @return array The SQL condition and its parameters.
     */
    private static function carrying_select(bool $peruser): array {
        global $DB;

        $select = $DB->sql_like('data', ':key');
        $params = ['key' => '%' . $DB->sql_like_escape('"' . snapshot::KEY . '"') . '%'];
        return [$peruser ? "userid = :userid AND $select" : $select, $params];
    }

    /**
     * The decoded data of an issue, only when it really carries the plugin's key.
     *
     * @param string|null $json The data column.
     * @return array|null The decoded object, null when it is not one or holds no copy.
     */
    private static function decode_carrying(?string $json): ?array {
        $data = json_decode((string) $json, true);
        return is_array($data) && array_key_exists(snapshot::KEY, $data) ? $data : null;
    }

    /**
     * Remove the plugin's key from the data of the issues a condition selects, and nothing else.
     *
     * @param string $select The condition, from {@see self::carrying_select()}.
     * @param array $params Its parameters.
     * @return void
     */
    private static function strip_key(string $select, array $params): void {
        global $DB;

        $issues = $DB->get_recordset_select('tool_certificate_issues', $select, $params, '', 'id, data');
        foreach ($issues as $issue) {
            $data = self::decode_carrying($issue->data);
            if ($data === null) {
                continue;
            }
            unset($data[snapshot::KEY]);
            $DB->set_field('tool_certificate_issues', 'data', $data ? json_encode($data) : '{}', ['id' => $issue->id]);
        }
        $issues->close();
    }
}
