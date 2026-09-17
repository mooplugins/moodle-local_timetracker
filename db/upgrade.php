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
 * Upgrade steps for local_timetracker.
 *
 * @package    local_timetracker
 * @copyright  2026 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the local_timetracker plugin.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_timetracker_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026090700) {
        // Rename legacy unprefixed tables used before the component-prefixed schema.
        $legacyconfig = new xmldb_table('timetracker');
        if ($dbman->table_exists($legacyconfig) && !$dbman->table_exists('local_timetracker')) {
            $dbman->rename_table($legacyconfig, 'local_timetracker');
        }

        $legacylog = new xmldb_table('timetracker_log');
        if ($dbman->table_exists($legacylog) && !$dbman->table_exists('local_timetracker_log')) {
            $dbman->rename_table($legacylog, 'local_timetracker_log');
        }

        upgrade_plugin_savepoint(true, 2026090700, 'local', 'timetracker');
    }

    if ($oldversion < 2026090800) {
        // Add finishedattempt index on log for compaction queries.
        $logtable = new xmldb_table('local_timetracker_log');
        $finindex = new xmldb_index('local_timetracker_log_fin_ix', XMLDB_INDEX_NOTUNIQUE, ['finishedattempt']);
        if ($dbman->table_exists($logtable) && !$dbman->index_exists($logtable, $finindex)) {
            $dbman->add_index($logtable, $finindex);
        }

        // Compacted closed-attempt report table.
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

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026090800, 'local', 'timetracker');
    }

    if ($oldversion < 2026090801) {
        // No schema changes — active/idle heartbeat autosave and activity percent setting.
        upgrade_plugin_savepoint(true, 2026090801, 'local', 'timetracker');
    }

    if ($oldversion < 2026090802) {
        // No schema changes — module scope all vs listed types.
        upgrade_plugin_savepoint(true, 2026090802, 'local', 'timetracker');
    }

    if ($oldversion < 2026090803) {
        // No schema changes — Time Tracker site report + viewreport capability.
        upgrade_plugin_savepoint(true, 2026090803, 'local', 'timetracker');
    }

    if ($oldversion < 2026090804) {
        // No schema changes — report UI refinements.
        upgrade_plugin_savepoint(true, 2026090804, 'local', 'timetracker');
    }

    if ($oldversion < 2026090805) {
        // No schema changes — include finished logs in totals; compact on finish.
        upgrade_plugin_savepoint(true, 2026090805, 'local', 'timetracker');
    }

    if ($oldversion < 2026090806) {
        // No schema changes — idle overlay no longer counted as active time.
        upgrade_plugin_savepoint(true, 2026090806, 'local', 'timetracker');
    }

    return true;
}
