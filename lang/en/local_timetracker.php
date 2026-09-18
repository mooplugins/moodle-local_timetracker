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
 * Language strings for local_timetracker.
 *
 * @package    local_timetracker
 * @author     BitKea Technologies LLP
 * @copyright  2026 BitKea Technologies LLP
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Time Tracker';
$string['title'] = 'Time Tracker';
$string['enabled'] = 'Enable Time Tracker';
$string['enabled_help'] = 'Enable or disable Time Tracker on this activity or resource.';
$string['idletime'] = 'Maximum idle time';
$string['idletime_help'] = 'After how many minutes of no activity on the screen the idle overlay should appear.';
$string['idletime_desc'] = 'After how many minutes of no activity on the screen the idle overlay should appear.';
$string['idletimeneglect'] = 'Allowed idle time';
$string['idletimeneglect_help'] = 'Number of minutes that should still be tracked when the idle overlay appears. This is usually set to how long a learner would take to go through a single page of this activity. It must be less than Maximum idle time.';
$string['idletimeneglect_desc'] = 'Number of minutes that should still be tracked when the idle overlay appears. This is usually set to how long a learner would take to go through a single page of this activity. It must be less than Maximum idle time.';
$string['idletext'] = 'Your time is not tracking due to inactivity.<br />You are on break since';

$string['globalsettingstitle'] = 'Time Tracker global settings';
$string['globalenabled'] = 'Enable Time Tracker';
$string['globalenabled_desc'] = 'Enable or disable Time Tracker globally.';
$string['finishattempttime'] = 'Idle session timeout';
$string['finishattempttime_desc'] = 'After how many minutes an attempt without activity should be finished by the scheduled task.';
$string['defaultsettingstitle'] = 'Default settings for activities and resources';
$string['modulescope'] = 'Activities that can use Time Tracker';
$string['modulescope_desc'] = 'Choose whether teachers can enable Time Tracker on every activity/resource, or only on the module types listed below.';
$string['modulescope_all'] = 'All activities and resources';
$string['modulescope_list'] = 'Only the listed module types';
$string['allowedmodules'] = 'Allowed activities/resources';
$string['allowedmodules_desc'] = 'Used when “Only the listed module types” is selected. Comma-separated module names (for example book,lesson,page,quiz,scorm,hvp). Teachers still enable Time Tracker per activity.';
$string['autosavetime'] = 'Auto save time';
$string['autosavetime_desc'] = 'Interval in minutes for heartbeat autosave of active and idle time (does not wait for the idle overlay).';
$string['activitypercent'] = 'Minimum activity percent';
$string['activitypercent_desc'] = 'For each autosave window, the share of seconds that must still count as active (within allowed idle grace, with key/mouse/touch/scroll signals). If engagement is below this percent, the whole window is saved as idle.';

$string['button_exit'] = 'Exit activity after saving tracked time';
$string['button_resume'] = 'Resume course';

$string['finish_idle_attempts'] = 'Finish idle Time Tracker attempts';
$string['compact_closed_attempts'] = 'Compact closed Time Tracker attempts into report table';

$string['timetracker:viewreport'] = 'View Time Tracker report';
$string['timetracker_report'] = 'Time Tracker report';
$string['timetracker_report_shortdesc'] = 'This report shows time tracked on enabled activities for enrolled users in a selected course.';
$string['name'] = 'Name';
$string['total_time_tracked'] = 'Total time tracked';
$string['activities_tracked'] = 'Activities with time';
$string['last_tracked_end'] = 'Last tracked end';
$string['never'] = '(never)';
$string['select_course'] = 'Select course';
$string['selectcourseprompt'] = 'Select a course to load the report.';
$string['searchplaceholder'] = 'Search a record';
$string['search'] = 'Go';
$string['export'] = 'Export';
$string['exportcsv'] = 'CSV';
$string['exportexcel'] = 'Excel';
$string['recordsperpage'] = 'Records per page';
$string['previous'] = 'Previous';
$string['next'] = 'Next';
$string['nodataavailable'] = 'No data available in table';
$string['showingrecords'] = 'Showing {$a->from} - {$a->to} of {$a->total}';
$string['loading'] = 'Loading...';

$string['privacy:metadata:local_timetracker'] = 'Stores Time Tracker configuration for course modules.';
$string['privacy:metadata:local_timetracker:course'] = 'The course the configuration belongs to.';
$string['privacy:metadata:local_timetracker:coursemodule'] = 'The course module being tracked.';
$string['privacy:metadata:local_timetracker:enabled'] = 'Whether tracking is enabled for the course module.';
$string['privacy:metadata:local_timetracker:idletime'] = 'Maximum idle time in minutes before the overlay appears.';
$string['privacy:metadata:local_timetracker:idletimeneglect'] = 'Allowed idle minutes still counted as learning time.';
$string['privacy:metadata:local_timetracker_log'] = 'Stores per-user timing logs for tracked activities (open/recent attempts).';
$string['privacy:metadata:local_timetracker_log:userid'] = 'The user the timing log belongs to.';
$string['privacy:metadata:local_timetracker_log:timetrackerid'] = 'The Time Tracker configuration this log row belongs to.';
$string['privacy:metadata:local_timetracker_log:sessionid'] = 'The browser session identifier for the attempt.';
$string['privacy:metadata:local_timetracker_log:element'] = 'The log element type (start.time, end.time, or idle.time).';
$string['privacy:metadata:local_timetracker_log:value'] = 'The stored value (timestamp or idle seconds).';
$string['privacy:metadata:local_timetracker_log:finishedattempt'] = 'Whether the attempt has been closed.';
$string['privacy:metadata:local_timetracker_log:timecreated'] = 'When the log row was created.';
$string['privacy:metadata:local_timetracker_log:timemodified'] = 'When the log row was last updated.';
$string['privacy:metadata:local_timetracker_report'] = 'Stores compacted closed-attempt time totals for reporting.';
$string['privacy:metadata:local_timetracker_report:userid'] = 'The user the report row belongs to.';
$string['privacy:metadata:local_timetracker_report:timetrackerid'] = 'The Time Tracker configuration this report row belongs to.';
$string['privacy:metadata:local_timetracker_report:course'] = 'The course the attempt belongs to.';
$string['privacy:metadata:local_timetracker_report:coursemodule'] = 'The course module the attempt belongs to.';
$string['privacy:metadata:local_timetracker_report:sessionid'] = 'The browser session identifier for the compacted attempt.';
$string['privacy:metadata:local_timetracker_report:timestart'] = 'When the attempt started.';
$string['privacy:metadata:local_timetracker_report:timeend'] = 'When the attempt ended.';
$string['privacy:metadata:local_timetracker_report:idleseconds'] = 'Idle seconds subtracted from the attempt.';
$string['privacy:metadata:local_timetracker_report:timespent'] = 'Active seconds tracked for the attempt.';
