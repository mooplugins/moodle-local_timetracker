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
 * Callbacks that Moodle loads automatically for local_timetracker.
 *
 * @package    local_timetracker
 * @author     BitKea Technologies LLP
 * @copyright  2026 BitKea Technologies LLP
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/timetracker/locallib.php');

/**
 * Add Time Tracker fields to the course module edit form.
 *
 * @param moodleform_mod $formwrapper
 * @param MoodleQuickForm $mform
 * @return void
 */
function local_timetracker_coursemodule_standard_elements($formwrapper, $mform) {
    global $DB;

    local_timetracker_ensure_schema();

    if (!get_config('local_timetracker', 'enabled')) {
        return;
    }

    $current = $formwrapper->get_current();
    $timetracker = null;
    if (!empty($current->instance) && !empty($current->coursemodule)) {
        $timetracker = $DB->get_record(LOCAL_TIMETRACKER_TABLE, [
            'course' => $current->course,
            'coursemodule' => $current->coursemodule,
        ]);
    }

    if (!local_timetracker_is_module_allowed($current->modulename)) {
        return;
    }

    $mform->addElement('header', 'timetracker', get_string('title', 'local_timetracker'));

    $mform->addElement('checkbox', 'enabled', get_string('enabled', 'local_timetracker'));
    $mform->setDefault('enabled', $timetracker ? $timetracker->enabled : 0);
    $mform->addHelpButton('enabled', 'enabled', 'local_timetracker');

    $mform->addElement('text', 'idletime', get_string('idletime', 'local_timetracker'), ['size' => 3]);
    $mform->setType('idletime', PARAM_INT);
    $mform->setDefault(
        'idletime',
        $timetracker ? $timetracker->idletime : get_config('local_timetracker', 'idletime')
    );
    $mform->addHelpButton('idletime', 'idletime', 'local_timetracker');

    $mform->addElement('text', 'idletimeneglect', get_string('idletimeneglect', 'local_timetracker'), ['size' => 3]);
    $mform->setType('idletimeneglect', PARAM_INT);
    $mform->setDefault(
        'idletimeneglect',
        $timetracker ? $timetracker->idletimeneglect : get_config('local_timetracker', 'idletimeneglect')
    );
    $mform->addHelpButton('idletimeneglect', 'idletimeneglect', 'local_timetracker');
}

/**
 * Persist Time Tracker settings after a course module is saved.
 *
 * @param stdClass $data
 * @param stdClass $course
 * @return stdClass
 */
function local_timetracker_coursemodule_edit_post_actions($data, $course) {
    global $DB;

    local_timetracker_ensure_schema();

    if (!get_config('local_timetracker', 'enabled')) {
        return $data;
    }

    if (!local_timetracker_is_module_allowed($data->modulename)) {
        return $data;
    }

    $enabled = !empty($data->enabled) ? 1 : 0;
    $idletime = isset($data->idletime) ? (int) $data->idletime : (int) get_config('local_timetracker', 'idletime');
    $idletimeneglect = isset($data->idletimeneglect)
        ? (int) $data->idletimeneglect
        : (int) get_config('local_timetracker', 'idletimeneglect');
    if ($idletime < 1) {
        $idletime = 1;
    }
    if ($idletimeneglect < 0) {
        $idletimeneglect = 0;
    }
    // Allowed idle must be shorter than maximum idle (overlay threshold).
    if ($idletimeneglect >= $idletime) {
        $idletimeneglect = max(0, $idletime - 1);
    }

    $existing = $DB->get_record(LOCAL_TIMETRACKER_TABLE, [
        'course' => $data->course,
        'coursemodule' => $data->coursemodule,
    ]);

    $record = (object) [
        'enabled' => $enabled,
        'idletime' => $idletime,
        'idletimeneglect' => $idletimeneglect,
        'timemodified' => time(),
    ];

    if ($existing) {
        $record->id = $existing->id;
        $DB->update_record(LOCAL_TIMETRACKER_TABLE, $record);
    } else {
        $record->course = $data->course;
        $record->coursemodule = $data->coursemodule;
        $record->timecreated = time();
        $DB->insert_record(LOCAL_TIMETRACKER_TABLE, $record);
    }

    return $data;
}
