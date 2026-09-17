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
 * Admin settings / navigation for local_timetracker.
 *
 * @package    local_timetracker
 * @copyright  2026 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Always register the report. Access is gated by local/timetracker:viewreport.
$ADMIN->add('reports', new admin_externalpage(
    'local_timetracker_report',
    get_string('timetracker_report', 'local_timetracker'),
    new moodle_url('/local/timetracker/report/index.php'),
    'local/timetracker:viewreport'
));

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_timetracker', get_string('pluginname', 'local_timetracker'));
    $ADMIN->add('localplugins', $settings);

    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_heading(
            'local_timetracker/global_title',
            get_string('globalsettingstitle', 'local_timetracker'),
            ''
        ));

        $settings->add(new admin_setting_configcheckbox(
            'local_timetracker/enabled',
            get_string('globalenabled', 'local_timetracker'),
            get_string('globalenabled_desc', 'local_timetracker'),
            1
        ));

        $settings->add(new admin_setting_configtext(
            'local_timetracker/finishattempttime',
            get_string('finishattempttime', 'local_timetracker'),
            get_string('finishattempttime_desc', 'local_timetracker'),
            '45',
            PARAM_INT
        ));

        $settings->add(new admin_setting_configselect(
            'local_timetracker/autosavetime',
            get_string('autosavetime', 'local_timetracker'),
            get_string('autosavetime_desc', 'local_timetracker'),
            '2',
            [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5]
        ));

        $settings->add(new admin_setting_configtext(
            'local_timetracker/activitypercent',
            get_string('activitypercent', 'local_timetracker'),
            get_string('activitypercent_desc', 'local_timetracker'),
            '20',
            PARAM_INT
        ));

        $settings->add(new admin_setting_heading(
            'local_timetracker/defaults_title',
            get_string('defaultsettingstitle', 'local_timetracker'),
            ''
        ));

        $settings->add(new admin_setting_configselect(
            'local_timetracker/modulescope',
            get_string('modulescope', 'local_timetracker'),
            get_string('modulescope_desc', 'local_timetracker'),
            'list',
            [
                'all' => get_string('modulescope_all', 'local_timetracker'),
                'list' => get_string('modulescope_list', 'local_timetracker'),
            ]
        ));

        $settings->add(new admin_setting_configtext(
            'local_timetracker/allowedmodules',
            get_string('allowedmodules', 'local_timetracker'),
            get_string('allowedmodules_desc', 'local_timetracker'),
            'book,lesson,page,quiz,scorm,hvp',
            PARAM_TEXT
        ));

        $settings->add(new admin_setting_configtext(
            'local_timetracker/idletime',
            get_string('idletime', 'local_timetracker'),
            get_string('idletime_desc', 'local_timetracker'),
            '8',
            PARAM_INT
        ));

        $settings->add(new admin_setting_configtext(
            'local_timetracker/idletimeneglect',
            get_string('idletimeneglect', 'local_timetracker'),
            get_string('idletimeneglect_desc', 'local_timetracker'),
            '4',
            PARAM_INT
        ));
    }
}
