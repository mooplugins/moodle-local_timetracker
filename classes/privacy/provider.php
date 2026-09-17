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
 * Privacy provider for local_timetracker.
 *
 * @package    local_timetracker
 * @copyright  2026 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_timetracker\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy subsystem implementation.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Describe stored personal data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_timetracker', [
            'course' => 'privacy:metadata:local_timetracker:course',
            'coursemodule' => 'privacy:metadata:local_timetracker:coursemodule',
            'enabled' => 'privacy:metadata:local_timetracker:enabled',
            'idletime' => 'privacy:metadata:local_timetracker:idletime',
            'idletimeneglect' => 'privacy:metadata:local_timetracker:idletimeneglect',
        ], 'privacy:metadata:local_timetracker');

        $collection->add_database_table('local_timetracker_log', [
            'userid' => 'privacy:metadata:local_timetracker_log:userid',
            'timetrackerid' => 'privacy:metadata:local_timetracker_log:timetrackerid',
            'sessionid' => 'privacy:metadata:local_timetracker_log:sessionid',
            'element' => 'privacy:metadata:local_timetracker_log:element',
            'value' => 'privacy:metadata:local_timetracker_log:value',
            'finishedattempt' => 'privacy:metadata:local_timetracker_log:finishedattempt',
            'timecreated' => 'privacy:metadata:local_timetracker_log:timecreated',
            'timemodified' => 'privacy:metadata:local_timetracker_log:timemodified',
        ], 'privacy:metadata:local_timetracker_log');

        $collection->add_database_table('local_timetracker_report', [
            'userid' => 'privacy:metadata:local_timetracker_report:userid',
            'timetrackerid' => 'privacy:metadata:local_timetracker_report:timetrackerid',
            'course' => 'privacy:metadata:local_timetracker_report:course',
            'coursemodule' => 'privacy:metadata:local_timetracker_report:coursemodule',
            'sessionid' => 'privacy:metadata:local_timetracker_report:sessionid',
            'timestart' => 'privacy:metadata:local_timetracker_report:timestart',
            'timeend' => 'privacy:metadata:local_timetracker_report:timeend',
            'idleseconds' => 'privacy:metadata:local_timetracker_report:idleseconds',
            'timespent' => 'privacy:metadata:local_timetracker_report:timespent',
        ], 'privacy:metadata:local_timetracker_report');

        return $collection;
    }

    /**
     * Get contexts containing user data.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {local_timetracker} t ON t.course = ctx.instanceid AND ctx.contextlevel = :level1
                  JOIN {local_timetracker_log} l ON l.timetrackerid = t.id
                 WHERE l.userid = :userid1
                 UNION
                SELECT ctx.id
                  FROM {context} ctx
                  JOIN {local_timetracker_report} r ON r.course = ctx.instanceid AND ctx.contextlevel = :level2
                 WHERE r.userid = :userid2";

        $contextlist->add_from_sql($sql, [
            'level1' => CONTEXT_COURSE,
            'level2' => CONTEXT_COURSE,
            'userid1' => $userid,
            'userid2' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Get users in a context.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_course) {
            return;
        }

        $sql = "SELECT l.userid
                  FROM {local_timetracker_log} l
                  JOIN {local_timetracker} t ON t.id = l.timetrackerid
                 WHERE t.course = :courseid
                 UNION
                SELECT r.userid
                  FROM {local_timetracker_report} r
                 WHERE r.course = :courseid2";
        $userlist->add_from_sql('userid', $sql, [
            'courseid' => $context->instanceid,
            'courseid2' => $context->instanceid,
        ]);
    }

    /**
     * Export user data for approved contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (!$contextlist->count()) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_course) {
                continue;
            }

            $logs = $DB->get_records_sql(
                "SELECT l.*
                   FROM {local_timetracker_log} l
                   JOIN {local_timetracker} t ON t.id = l.timetrackerid
                  WHERE t.course = :courseid AND l.userid = :userid
               ORDER BY l.id ASC",
                ['courseid' => $context->instanceid, 'userid' => $userid]
            );

            $reports = $DB->get_records('local_timetracker_report', [
                'course' => $context->instanceid,
                'userid' => $userid,
            ]);

            if ($logs || $reports) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_timetracker')],
                    (object) [
                        'logs' => array_values($logs),
                        'reports' => array_values($reports),
                    ]
                );
            }
        }
    }

    /**
     * Delete all data for all users in a context.
     *
     * @param \context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if (!$context instanceof \context_course) {
            return;
        }

        $trackerids = $DB->get_fieldset_select('local_timetracker', 'id', 'course = ?', [$context->instanceid]);
        if (!empty($trackerids)) {
            list($insql, $inparams) = $DB->get_in_or_equal($trackerids);
            $DB->delete_records_select('local_timetracker_log', "timetrackerid $insql", $inparams);
        }

        $DB->delete_records('local_timetracker_report', ['course' => $context->instanceid]);
    }

    /**
     * Delete all user data for the specified user, in the approved contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        if (!$contextlist->count()) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_course) {
                continue;
            }

            $trackerids = $DB->get_fieldset_select('local_timetracker', 'id', 'course = ?', [$context->instanceid]);
            if (!empty($trackerids)) {
                list($insql, $inparams) = $DB->get_in_or_equal($trackerids, SQL_PARAMS_NAMED);
                $params = array_merge(['userid' => $userid], $inparams);
                $DB->delete_records_select(
                    'local_timetracker_log',
                    "userid = :userid AND timetrackerid $insql",
                    $params
                );
            }

            $DB->delete_records('local_timetracker_report', [
                'course' => $context->instanceid,
                'userid' => $userid,
            ]);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof \context_course) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        list($usersql, $userparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'user');

        $trackerids = $DB->get_fieldset_select('local_timetracker', 'id', 'course = ?', [$context->instanceid]);
        if (!empty($trackerids)) {
            list($trackersql, $trackerparams) = $DB->get_in_or_equal($trackerids, SQL_PARAMS_NAMED, 'tracker');
            $params = array_merge($userparams, $trackerparams);
            $DB->delete_records_select(
                'local_timetracker_log',
                "userid $usersql AND timetrackerid $trackersql",
                $params
            );
        }

        $params = array_merge(['courseid' => $context->instanceid], $userparams);
        $DB->delete_records_select(
            'local_timetracker_report',
            "course = :courseid AND userid $usersql",
            $params
        );
    }
}
