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
 * Local functions used in this plugin.
 *
 * @package    local_timetracker
 * @author     BitKea Technologies LLP
 * @copyright  2026 BitKea Technologies LLP
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('LOCAL_TIMETRACKER_TABLE', 'local_timetracker');
define('LOCAL_TIMETRACKER_LOG_TABLE', 'local_timetracker_log');
define('LOCAL_TIMETRACKER_REPORT_TABLE', 'local_timetracker_report');

/**
 * Ensure packaged table names/schema exist (rename legacy + create report).
 *
 * Safe to call repeatedly. Fixes "Error reading from database" when code
 * expects local_timetracker* but the site has not visited Notifications yet.
 *
 * @return void
 */
function local_timetracker_ensure_schema(): void {
    global $DB;

    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    // Only patch schema after the plugin is installed (avoids racing Moodle install.xml).
    $installed = get_config('local_timetracker', 'version');
    if (empty($installed)) {
        return;
    }

    $dbman = $DB->get_manager();

    $legacyconfig = new xmldb_table('timetracker');
    if ($dbman->table_exists($legacyconfig) && !$dbman->table_exists('local_timetracker')) {
        $dbman->rename_table($legacyconfig, 'local_timetracker');
    }

    $legacylog = new xmldb_table('timetracker_log');
    if ($dbman->table_exists($legacylog) && !$dbman->table_exists('local_timetracker_log')) {
        $dbman->rename_table($legacylog, 'local_timetracker_log');
    }

    $logtable = new xmldb_table('local_timetracker_log');
    if ($dbman->table_exists($logtable)) {
        $finindex = new xmldb_index('local_timetracker_log_fin_ix', XMLDB_INDEX_NOTUNIQUE, ['finishedattempt']);
        if (!$dbman->index_exists($logtable, $finindex)) {
            $dbman->add_index($logtable, $finindex);
        }
    }

    if (!$dbman->table_exists('local_timetracker_report')) {
        $table = new xmldb_table('local_timetracker_report');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timetrackerid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('course', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('coursemodule', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sessionid', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, '');
        $table->add_field('timestart', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timeend', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('idleseconds', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timespent', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('local_timetracker_rep_utt_ix', XMLDB_INDEX_NOTUNIQUE, ['userid', 'timetrackerid']);
        $table->add_index('local_timetracker_rep_course_ix', XMLDB_INDEX_NOTUNIQUE, ['course']);
        $table->add_index('local_timetracker_rep_cm_ix', XMLDB_INDEX_NOTUNIQUE, ['coursemodule']);
        $table->add_index(
            'local_timetracker_rep_uniq_ix',
            XMLDB_INDEX_UNIQUE,
            ['userid', 'timetrackerid', 'sessionid', 'timestart']
        );
        $dbman->create_table($table);
    }
}

/**
 * Whether Time Tracker UI/config is allowed for a module name.
 *
 * Scope setting:
 * - all  → any module (except empty name)
 * - list → only names in allowedmodules (comma-separated)
 *
 * @param string $modulename
 * @return bool
 */
function local_timetracker_is_module_allowed(string $modulename): bool {
    $modulename = trim($modulename);
    if ($modulename === '') {
        return false;
    }

    $scope = get_config('local_timetracker', 'modulescope');
    if ($scope === false || $scope === null || $scope === '') {
        $scope = 'list';
    }

    if ($scope === 'all') {
        return true;
    }

    $allowed = array_filter(array_map('trim', explode(',', (string) get_config('local_timetracker', 'allowedmodules'))));
    return in_array($modulename, $allowed, true);
}

/**
 * Current browser session identifier used to group log rows.
 *
 * @return string
 */
function local_timetracker_current_sessionid(): string {
    $sid = session_id();
    if ($sid !== '') {
        return $sid;
    }

    return '';
}

/**
 * Open end.time log rows across all users (for cron).
 *
 * @return array
 */
function timetracker_get_open_attempts() {
    global $DB;

    local_timetracker_ensure_schema();

    return $DB->get_records(LOCAL_TIMETRACKER_LOG_TABLE, [
        'finishedattempt' => 0,
        'element' => 'end.time',
    ]);
}

/**
 * Open end.time log rows for the current user and session.
 *
 * @return array
 */
function timetracker_get_session_open_attempts() {
    global $DB, $USER;

    local_timetracker_ensure_schema();

    $sessionid = local_timetracker_current_sessionid();
    if ($sessionid === '') {
        return [];
    }

    return $DB->get_records(LOCAL_TIMETRACKER_LOG_TABLE, [
        'finishedattempt' => 0,
        'element' => 'end.time',
        'userid' => $USER->id,
        'sessionid' => $sessionid,
    ]);
}

/**
 * Most recent open end.time row for the current user and session.
 *
 * @return stdClass|false
 */
function timetracker_get_last_session_open_attempt() {
    global $DB, $USER;

    local_timetracker_ensure_schema();

    $sessionid = local_timetracker_current_sessionid();
    if ($sessionid === '') {
        return false;
    }

    $sql = "SELECT *
              FROM {" . LOCAL_TIMETRACKER_LOG_TABLE . "}
             WHERE userid = :userid
               AND sessionid = :sessionid
               AND element = :element
               AND finishedattempt = 0
          ORDER BY id DESC";

    $records = $DB->get_records_sql($sql, [
        'userid' => $USER->id,
        'sessionid' => $sessionid,
        'element' => 'end.time',
    ], 0, 1);

    return $records ? reset($records) : false;
}

/**
 * Tracker configuration by id.
 *
 * @param int $timetrackerid
 * @return stdClass|false
 */
function timetracker_get_tracker_details($timetrackerid) {
    global $DB;

    local_timetracker_ensure_schema();

    return $DB->get_record(LOCAL_TIMETRACKER_TABLE, ['id' => $timetrackerid]);
}

/**
 * Mark all open log rows for an attempt as finished and compact them into the report table.
 *
 * @param int $userid
 * @param int $timetrackerid
 * @param string $sessionid
 * @return void
 */
function timetracker_finish_attempt($userid, $timetrackerid, $sessionid) {
    global $DB;

    local_timetracker_ensure_schema();

    $DB->set_field_select(
        LOCAL_TIMETRACKER_LOG_TABLE,
        'finishedattempt',
        1,
        'userid = :userid AND timetrackerid = :timetrackerid AND sessionid = :sessionid',
        [
            'userid' => $userid,
            'timetrackerid' => $timetrackerid,
            'sessionid' => $sessionid,
        ]
    );

    // Move this closed attempt into the report table immediately so totals are not
    // stuck at 0 until the scheduled compact task runs.
    local_timetracker_compact_closed_attempts(50);
}

/**
 * Sum compacted timespent seconds from the report table.
 *
 * @param int $userid
 * @param int|null $timetrackerid Optional tracker filter.
 * @param int|null $courseid Optional course filter.
 * @param int|null $coursemodule Optional course module filter.
 * @return int
 */
function local_timetracker_report_timespent(
    int $userid,
    ?int $timetrackerid = null,
    ?int $courseid = null,
    ?int $coursemodule = null
): int {
    global $DB;

    local_timetracker_ensure_schema();

    if (!$DB->get_manager()->table_exists(LOCAL_TIMETRACKER_REPORT_TABLE)) {
        return 0;
    }

    $conditions = ['userid = :userid'];
    $params = ['userid' => $userid];

    if ($timetrackerid !== null) {
        $conditions[] = 'timetrackerid = :timetrackerid';
        $params['timetrackerid'] = $timetrackerid;
    }
    if ($courseid !== null) {
        $conditions[] = 'course = :courseid';
        $params['courseid'] = $courseid;
    }
    if ($coursemodule !== null) {
        $conditions[] = 'coursemodule = :coursemodule';
        $params['coursemodule'] = $coursemodule;
    }

    $sql = "SELECT COALESCE(SUM(timespent), 0)
              FROM {" . LOCAL_TIMETRACKER_REPORT_TABLE . "}
             WHERE " . implode(' AND ', $conditions);

    return (int) $DB->get_field_sql($sql, $params);
}

/**
 * Sum active seconds from log attempts (open and/or finished, not yet compacted).
 *
 * @param int $userid
 * @param int|null $timetrackerid
 * @param int|null $courseid
 * @param int|null $coursemodule
 * @param int|null $finishedattempt Null = all log attempts; 0 = open; 1 = finished.
 * @return int
 */
function local_timetracker_log_timespent(
    int $userid,
    ?int $timetrackerid = null,
    ?int $courseid = null,
    ?int $coursemodule = null,
    ?int $finishedattempt = null
): int {
    global $DB;

    local_timetracker_ensure_schema();

    $params = [
        'userid' => $userid,
        'startelement' => 'start.time',
        'endelement' => 'end.time',
        'idleelement' => 'idle.time',
    ];

    $trackerjoin = '';
    $extra = '';
    if ($courseid !== null || $coursemodule !== null) {
        $trackerjoin = " JOIN {" . LOCAL_TIMETRACKER_TABLE . "} t ON t.id = a.timetrackerid ";
        if ($courseid !== null) {
            $extra .= " AND t.course = :courseid ";
            $params['courseid'] = $courseid;
        }
        if ($coursemodule !== null) {
            $extra .= " AND t.coursemodule = :coursemodule ";
            $params['coursemodule'] = $coursemodule;
        }
    }

    if ($timetrackerid !== null) {
        $extra .= " AND a.timetrackerid = :timetrackerid ";
        $params['timetrackerid'] = $timetrackerid;
    }

    $finishedsql = '';
    if ($finishedattempt !== null) {
        $finishedsql = ' AND a.finishedattempt = :afinished
                         AND b.finishedattempt = :bfinished
                         AND c.finishedattempt = :cfinished ';
        $params['afinished'] = $finishedattempt;
        $params['bfinished'] = $finishedattempt;
        $params['cfinished'] = $finishedattempt;
    }

    $sql = "SELECT a.id, (b.value - a.value - c.value) AS timedifference
              FROM {" . LOCAL_TIMETRACKER_LOG_TABLE . "} a
              JOIN {" . LOCAL_TIMETRACKER_LOG_TABLE . "} b
                ON b.userid = a.userid
               AND b.timetrackerid = a.timetrackerid
               AND b.sessionid = a.sessionid
               AND b.timecreated = a.timecreated
               AND b.element = :endelement
              JOIN {" . LOCAL_TIMETRACKER_LOG_TABLE . "} c
                ON c.userid = a.userid
               AND c.timetrackerid = a.timetrackerid
               AND c.sessionid = a.sessionid
               AND c.timecreated = a.timecreated
               AND c.element = :idleelement
              $trackerjoin
             WHERE a.element = :startelement
               AND a.userid = :userid
               $finishedsql
               $extra";

    $rows = $DB->get_records_sql($sql, $params);
    $total = 0;
    foreach ($rows as $row) {
        $diff = (int) $row->timedifference;
        if ($diff > 0) {
            $total += $diff;
        }
    }

    return $total;
}

/**
 * Sum active seconds still sitting in open (unfinished) log attempts.
 *
 * @param int $userid
 * @param int|null $timetrackerid
 * @param int|null $courseid
 * @param int|null $coursemodule
 * @return int
 */
function local_timetracker_open_log_timespent(
    int $userid,
    ?int $timetrackerid = null,
    ?int $courseid = null,
    ?int $coursemodule = null
): int {
    return local_timetracker_log_timespent($userid, $timetrackerid, $courseid, $coursemodule, 0);
}

/**
 * Total tracked seconds for a user (compacted report + uncompacted logs).
 *
 * Includes open attempts and finished attempts that have not yet been compacted,
 * so totals stay correct between leave and the compact scheduled task.
 *
 * @param int $userid
 * @param int|null $timetrackerid
 * @param int|null $courseid
 * @param int|null $coursemodule
 * @return int
 */
function local_timetracker_get_timespent(
    int $userid,
    ?int $timetrackerid = null,
    ?int $courseid = null,
    ?int $coursemodule = null
): int {
    local_timetracker_ensure_schema();

    return local_timetracker_report_timespent($userid, $timetrackerid, $courseid, $coursemodule)
        + local_timetracker_log_timespent($userid, $timetrackerid, $courseid, $coursemodule, null);
}

/**
 * Compact closed log attempts into the report table and delete those log rows.
 *
 * @param int $limit Max closed attempt groups to process per run.
 * @return int Number of attempts compacted.
 */
function local_timetracker_compact_closed_attempts(int $limit = 500): int {
    global $DB;

    local_timetracker_ensure_schema();

    $sql = "SELECT a.id AS startid,
                   b.id AS endid,
                   c.id AS idleid,
                   a.userid,
                   a.timetrackerid,
                   a.sessionid,
                   a.timecreated AS attemptcreated,
                   a.value AS timestart,
                   b.value AS timeend,
                   c.value AS idleseconds,
                   t.course,
                   t.coursemodule
              FROM {" . LOCAL_TIMETRACKER_LOG_TABLE . "} a
              JOIN {" . LOCAL_TIMETRACKER_LOG_TABLE . "} b
                ON b.userid = a.userid
               AND b.timetrackerid = a.timetrackerid
               AND b.sessionid = a.sessionid
               AND b.timecreated = a.timecreated
               AND b.element = :endelement
               AND b.finishedattempt = 1
              JOIN {" . LOCAL_TIMETRACKER_LOG_TABLE . "} c
                ON c.userid = a.userid
               AND c.timetrackerid = a.timetrackerid
               AND c.sessionid = a.sessionid
               AND c.timecreated = a.timecreated
               AND c.element = :idleelement
               AND c.finishedattempt = 1
              JOIN {" . LOCAL_TIMETRACKER_TABLE . "} t ON t.id = a.timetrackerid
             WHERE a.element = :startelement
               AND a.finishedattempt = 1
          ORDER BY a.id ASC";

    $attempts = $DB->get_records_sql($sql, [
        'endelement' => 'end.time',
        'idleelement' => 'idle.time',
        'startelement' => 'start.time',
    ], 0, $limit);

    if (!$attempts) {
        return 0;
    }

    $compacted = 0;
    $now = time();

    foreach ($attempts as $attempt) {
        $timespent = (int) $attempt->timeend - (int) $attempt->timestart - (int) $attempt->idleseconds;
        if ($timespent < 0) {
            $timespent = 0;
        }

        $existing = $DB->get_record(LOCAL_TIMETRACKER_REPORT_TABLE, [
            'userid' => $attempt->userid,
            'timetrackerid' => $attempt->timetrackerid,
            'sessionid' => $attempt->sessionid,
            'timestart' => $attempt->timestart,
        ]);

        $transaction = $DB->start_delegated_transaction();

        if ($existing) {
            $existing->timeend = (int) $attempt->timeend;
            $existing->idleseconds = (int) $attempt->idleseconds;
            $existing->timespent = $timespent;
            $existing->course = (int) $attempt->course;
            $existing->coursemodule = (int) $attempt->coursemodule;
            $existing->timemodified = $now;
            $DB->update_record(LOCAL_TIMETRACKER_REPORT_TABLE, $existing);
        } else {
            $record = (object) [
                'userid' => (int) $attempt->userid,
                'timetrackerid' => (int) $attempt->timetrackerid,
                'course' => (int) $attempt->course,
                'coursemodule' => (int) $attempt->coursemodule,
                'sessionid' => (string) $attempt->sessionid,
                'timestart' => (int) $attempt->timestart,
                'timeend' => (int) $attempt->timeend,
                'idleseconds' => (int) $attempt->idleseconds,
                'timespent' => $timespent,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $DB->insert_record(LOCAL_TIMETRACKER_REPORT_TABLE, $record);
        }

        $DB->delete_records_list(LOCAL_TIMETRACKER_LOG_TABLE, 'id', [
            (int) $attempt->startid,
            (int) $attempt->endid,
            (int) $attempt->idleid,
        ]);

        $transaction->allow_commit();
        $compacted++;
    }

    return $compacted;
}

/**
 * Total tracked seconds for the current user on a tracker (report + open logs).
 *
 * @param stdClass $timetracker
 * @param mixed $cm Unused (kept for backward compatibility).
 * @param mixed $course Unused (kept for backward compatibility).
 * @return int
 */
function timetracker_timecompleted($timetracker, $cm = null, $course = null) {
    global $USER;

    if (empty($timetracker->id)) {
        return 0;
    }

    return local_timetracker_get_timespent((int) $USER->id, (int) $timetracker->id);
}

/**
 * Time spent on a course module for a user.
 *
 * @param stdClass $cm
 * @param stdClass $user
 * @return int|false
 */
function timetracker_timespent_by_cm($cm, $user) {
    if (empty($cm->id) || empty($user->id)) {
        return false;
    }

    $total = local_timetracker_get_timespent((int) $user->id, null, null, (int) $cm->id);
    return $total > 0 ? $total : false;
}

/**
 * Time spent by the current user across all trackers in the current course.
 *
 * @return int|false
 */
function timetracker_user_timespent_by_course() {
    global $COURSE, $USER;

    $total = local_timetracker_get_timespent((int) $USER->id, null, (int) $COURSE->id);
    return $total > 0 ? $total : false;
}

/**
 * Legacy helper: total for trackers in a course for the current user.
 *
 * @param int $courseid
 * @return int
 */
function timetracker_timecompleted_by_course_id($courseid) {
    global $USER;

    return local_timetracker_get_timespent((int) $USER->id, null, (int) $courseid);
}

/**
 * Create or update timing log rows for the current user/session.
 *
 * @param stdClass $timetracker
 * @param int $finishedattempt
 * @param int $idletime
 * @return bool
 */
function timetracker_log($timetracker, $finishedattempt = 0, $idletime = 0) {
    global $DB, $USER;

    local_timetracker_ensure_schema();

    $sessionid = local_timetracker_current_sessionid();
    if ($sessionid === '' || empty($timetracker->id)) {
        return false;
    }

    $now = time();
    $base = [
        'userid' => $USER->id,
        'timetrackerid' => $timetracker->id,
        'sessionid' => $sessionid,
        'finishedattempt' => 0,
    ];

    $track = $DB->get_record(LOCAL_TIMETRACKER_LOG_TABLE, $base + ['element' => 'start.time']);
    if ($track) {
        $trackend = $DB->get_record(LOCAL_TIMETRACKER_LOG_TABLE, $base + ['element' => 'end.time']);
        if ($trackend) {
            $trackend->value = $now;
            $trackend->timemodified = $now;
            if ($finishedattempt) {
                $trackend->finishedattempt = 1;
            }
            $DB->update_record(LOCAL_TIMETRACKER_LOG_TABLE, $trackend);
        }

        $postedidle = optional_param('timetracker_idle_time', 0, PARAM_INT);
        if ($postedidle > 0 || $idletime > 0) {
            if ($postedidle > 0) {
                $idletime = $postedidle;
            }
            $trackidle = $DB->get_record(LOCAL_TIMETRACKER_LOG_TABLE, $base + ['element' => 'idle.time']);
            if ($trackidle) {
                $trackidle->value += (int) $idletime;
                $trackidle->timemodified = $now;
                if ($finishedattempt) {
                    $trackidle->finishedattempt = 1;
                }
                $DB->update_record(LOCAL_TIMETRACKER_LOG_TABLE, $trackidle);
            }
        }

        if ($finishedattempt) {
            $track->timemodified = $now;
            $track->finishedattempt = 1;
            $DB->update_record(LOCAL_TIMETRACKER_LOG_TABLE, $track);

            $trackidle = $DB->get_record(LOCAL_TIMETRACKER_LOG_TABLE, $base + ['element' => 'idle.time']);
            if ($trackidle) {
                $trackidle->timemodified = $now;
                $trackidle->finishedattempt = 1;
                $DB->update_record(LOCAL_TIMETRACKER_LOG_TABLE, $trackidle);
            }
        }
    } else {
        foreach (['start.time' => $now, 'end.time' => $now, 'idle.time' => 0] as $element => $value) {
            $row = (object) [
                'userid' => $USER->id,
                'timetrackerid' => $timetracker->id,
                'sessionid' => $sessionid,
                'element' => $element,
                'value' => $value,
                'finishedattempt' => 0,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $DB->insert_record(LOCAL_TIMETRACKER_LOG_TABLE, $row);
        }
    }

    return true;
}

/**
 * Require capability to view the Time Tracker report.
 *
 * @param context|null $context
 * @return void
 */
function local_timetracker_require_view_report(?context $context = null): void {
    if ($context === null) {
        $context = context_system::instance();
    }
    require_capability('local/timetracker:viewreport', $context);
}

/**
 * Format seconds as a readable duration.
 *
 * @param int|null $duration
 * @return string
 */
function local_timetracker_format_duration($duration): string {
    $duration = max(0, (int) $duration);
    $hours = intdiv($duration, 3600);
    $minutes = intdiv($duration % 3600, 60);
    $seconds = $duration % 60;
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
}

/**
 * Format a unix timestamp for display.
 *
 * @param int $datetime
 * @return string
 */
function local_timetracker_format_datetime(int $datetime): string {
    if (!$datetime) {
        return get_string('never', 'local_timetracker');
    }
    return userdate($datetime);
}

/**
 * Table headers for the course report.
 *
 * @return array
 */
function local_timetracker_report_index_header(): array {
    return [
        ['name' => '#', 'key' => '#'],
        ['name' => get_string('name', 'local_timetracker'), 'key' => 'name'],
        ['name' => get_string('total_time_tracked', 'local_timetracker'), 'key' => 'total_time_tracked'],
        ['name' => get_string('activities_tracked', 'local_timetracker'), 'key' => 'activities_tracked'],
        ['name' => get_string('last_tracked_end', 'local_timetracker'), 'key' => 'last_tracked_end'],
    ];
}

/**
 * Enrolled users for the Time Tracker report.
 *
 * @param int $courseid
 * @param string $searchdata
 * @param int $limitfrom
 * @param int $limitnum Zero means no limit.
 * @return array{users: array, total: int}
 */
function local_timetracker_get_report_users(
    int $courseid,
    string $searchdata = '',
    int $limitfrom = 0,
    int $limitnum = 0
): array {
    global $DB;

    $coursecontext = context_course::instance($courseid);
    [$esql, $params] = get_enrolled_sql($coursecontext);

    $where = 'u.deleted = 0';
    if ($searchdata !== '') {
        $likesql = $DB->sql_like('u.firstname', ':search1', false)
            . ' OR ' . $DB->sql_like('u.lastname', ':search2', false)
            . ' OR ' . $DB->sql_like('u.email', ':search3', false)
            . ' OR ' . $DB->sql_like('u.username', ':search4', false);
        $where .= " AND ($likesql)";
        $params['search1'] = '%' . $DB->sql_like_escape($searchdata) . '%';
        $params['search2'] = $params['search1'];
        $params['search3'] = $params['search1'];
        $params['search4'] = $params['search1'];
    }

    $sqlcount = "SELECT COUNT(u.id)
                   FROM {user} u
                   JOIN ($esql) je ON je.id = u.id
                  WHERE $where";
    $total = (int) $DB->count_records_sql($sqlcount, $params);

    $usernamefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false);
    [$sort, $sortparams] = users_order_by_sql('u');
    $params = array_merge($params, $sortparams);

    $sql = "SELECT u.id, {$usernamefields->selects}
              FROM {user} u
              JOIN ($esql) je ON je.id = u.id
             WHERE $where
          ORDER BY $sort";

    if ($limitnum) {
        $users = $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
    } else {
        $users = $DB->get_records_sql($sql, $params);
    }

    return ['users' => $users, 'total' => $total];
}

/**
 * Last tracked end timestamp for a user in a course (report + open logs).
 *
 * @param int $userid
 * @param int $courseid
 * @return int
 */
function local_timetracker_last_tracked_end(int $userid, int $courseid): int {
    global $DB;

    local_timetracker_ensure_schema();

    $last = 0;
    if ($DB->get_manager()->table_exists(LOCAL_TIMETRACKER_REPORT_TABLE)) {
        $reportend = $DB->get_field_sql(
            "SELECT MAX(timeend)
               FROM {" . LOCAL_TIMETRACKER_REPORT_TABLE . "}
              WHERE userid = ? AND course = ?",
            [$userid, $courseid]
        );
        $last = max($last, (int) $reportend);
    }

    $logend = $DB->get_field_sql(
        "SELECT MAX(l.value)
           FROM {" . LOCAL_TIMETRACKER_LOG_TABLE . "} l
           JOIN {" . LOCAL_TIMETRACKER_TABLE . "} t ON t.id = l.timetrackerid
          WHERE l.userid = ?
            AND t.course = ?
            AND l.element = 'end.time'",
        [$userid, $courseid]
    );
    $last = max($last, (int) $logend);

    return $last;
}

/**
 * Count of enabled course modules with any tracked time for a user.
 *
 * @param int $userid
 * @param int $courseid
 * @return int
 */
function local_timetracker_activities_with_time(int $userid, int $courseid): int {
    global $DB;

    local_timetracker_ensure_schema();

    $trackers = $DB->get_records(LOCAL_TIMETRACKER_TABLE, [
        'course' => $courseid,
        'enabled' => 1,
    ], '', 'id, coursemodule');
    if (!$trackers) {
        return 0;
    }

    $count = 0;
    foreach ($trackers as $tracker) {
        $seconds = local_timetracker_get_timespent($userid, (int) $tracker->id, $courseid);
        if ($seconds > 0) {
            $count++;
        }
    }
    return $count;
}

/**
 * Report row data for one enrolled user in a course.
 *
 * @param int $courseid
 * @param stdClass $user
 * @return array{fullname: string, duration: string, durationseconds: int, activities: int, lastend: string, lastendts: int}
 */
function local_timetracker_prepare_user_report_data(int $courseid, stdClass $user): array {
    $seconds = local_timetracker_get_timespent((int) $user->id, null, $courseid);
    $lastend = local_timetracker_last_tracked_end((int) $user->id, $courseid);
    $activities = local_timetracker_activities_with_time((int) $user->id, $courseid);

    return [
        'fullname' => fullname($user),
        'duration' => local_timetracker_format_duration($seconds),
        'durationseconds' => $seconds,
        'activities' => $activities,
        'lastend' => local_timetracker_format_datetime($lastend),
        'lastendts' => $lastend,
    ];
}

/**
 * Neutralise formula-triggering characters for spreadsheet export.
 *
 * @param string $data
 * @return string
 */
function local_timetracker_clean_export_data(string $data): string {
    $cleaneddata = str_replace('=', "'='", $data);
    $cleaneddata = str_replace('+', "'+'", $cleaneddata);
    $cleaneddata = str_replace('-', "'-'", $cleaneddata);
    $cleaneddata = str_replace('@', "'@'", $cleaneddata);
    return mb_convert_encoding($cleaneddata, 'UTF-8', 'UTF-8');
}
