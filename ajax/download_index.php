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
 * Download Time Tracker report.
 *
 * @package    local_timetracker
 * @copyright  2026 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/timetracker/locallib.php');

require_login();
local_timetracker_require_view_report();
require_sesskey();

$dataformat = required_param('dataformat', PARAM_ALPHA);
$searchdata = optional_param('searchdata', '', PARAM_TEXT);
$courseid = required_param('courseid', PARAM_INT);

$columns = [];
foreach (local_timetracker_report_index_header() as $header) {
    if ($header['key'] === '#') {
        continue;
    }
    $columns[$header['key']] = $header['name'];
}

$filename = strtolower(get_string('pluginname', 'local_timetracker') . '_' . date('dMY'));
$filename = str_replace(' ', '_', $filename);

$rows = [];
if ($courseid && $courseid !== (int) SITEID) {
    local_timetracker_ensure_schema();
    $report = local_timetracker_get_report_users($courseid, $searchdata);
    foreach ($report['users'] as $user) {
        $details = local_timetracker_prepare_user_report_data($courseid, $user);
        $rows[] = [
            'name' => local_timetracker_clean_export_data($details['fullname']),
            'total_time_tracked' => $details['duration'],
            'activities_tracked' => $details['activities'],
            'last_tracked_end' => strip_tags($details['lastend']),
        ];
    }
}

\core\dataformat::download_data($filename, $dataformat, $columns, $rows);
