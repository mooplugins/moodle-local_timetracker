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
 * Web service functions for Time Tracker.
 *
 * @package    local_timetracker
 * @copyright  2026 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_timetracker\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use context_module;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../locallib.php');

/**
 * External API for Time Tracker.
 */
class tracker extends external_api {
    /**
     * Resolve tracker + course module context for write helpers.
     *
     * @param int $trackerid
     * @return stdClass
     */
    protected static function require_tracker_access(int $trackerid): \stdClass {
        $timetracker = timetracker_get_tracker_details($trackerid);
        if (!$timetracker || empty($timetracker->enabled)) {
            throw new moodle_exception('invalidrecord', 'error');
        }

        $cm = get_coursemodule_from_id(null, $timetracker->coursemodule, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_login($cm->course, false, $cm);

        return $timetracker;
    }

    /**
     * Save idle time for the current attempt (legacy overlay autosave).
     *
     * @param int $trackerid Time tracker configuration id.
     * @param int $idletime Idle seconds to add.
     * @return array
     */
    public static function save_idle_time($trackerid, $idletime) {
        $params = self::validate_parameters(self::save_idle_time_parameters(), [
            'trackerid' => $trackerid,
            'idletime' => $idletime,
        ]);

        $timetracker = self::require_tracker_access($params['trackerid']);
        timetracker_log($timetracker, 0, max(0, (int) $params['idletime']));

        return ['success' => true];
    }

    /**
     * Parameters for save_idle_time.
     *
     * @return external_function_parameters
     */
    public static function save_idle_time_parameters() {
        return new external_function_parameters([
            'trackerid' => new external_value(PARAM_INT, 'Time tracker ID'),
            'idletime' => new external_value(PARAM_INT, 'Idle time to save'),
        ]);
    }

    /**
     * Return structure for save_idle_time.
     *
     * @return external_single_structure
     */
    public static function save_idle_time_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
        ]);
    }

    /**
     * Heartbeat: advance end.time and optionally add idle seconds.
     *
     * Activity signal counts are accepted for auditing / future assign heuristics
     * but are not persisted as personal content (counts only, no key data).
     *
     * @param int $trackerid
     * @param int $idleseconds
     * @param int $activeseconds
     * @param int $keystrokes
     * @param int $clicks
     * @param int $mousemoves
     * @param int $scrolls
     * @param int $touches
     * @param int $windowseconds
     * @return array
     */
    public static function save_heartbeat(
        $trackerid,
        $idleseconds,
        $activeseconds = 0,
        $keystrokes = 0,
        $clicks = 0,
        $mousemoves = 0,
        $scrolls = 0,
        $touches = 0,
        $windowseconds = 0
    ) {
        $params = self::validate_parameters(self::save_heartbeat_parameters(), [
            'trackerid' => $trackerid,
            'idleseconds' => $idleseconds,
            'activeseconds' => $activeseconds,
            'keystrokes' => $keystrokes,
            'clicks' => $clicks,
            'mousemoves' => $mousemoves,
            'scrolls' => $scrolls,
            'touches' => $touches,
            'windowseconds' => $windowseconds,
        ]);

        $timetracker = self::require_tracker_access($params['trackerid']);

        // Cap idle to the reported window when provided, to limit bad clients.
        $idle = max(0, (int) $params['idleseconds']);
        if ((int) $params['windowseconds'] > 0) {
            $idle = min($idle, (int) $params['windowseconds']);
        }

        // Always touch end.time; add idle when the window was (partly) inactive.
        timetracker_log($timetracker, 0, $idle);

        // Activity counts are intentionally not stored (privacy). Available for future use.
        return [
            'success' => true,
            'idleseconds' => $idle,
            'activeseconds' => max(0, (int) $params['activeseconds']),
        ];
    }

    /**
     * Parameters for save_heartbeat.
     *
     * @return external_function_parameters
     */
    public static function save_heartbeat_parameters() {
        return new external_function_parameters([
            'trackerid' => new external_value(PARAM_INT, 'Time tracker ID'),
            'idleseconds' => new external_value(PARAM_INT, 'Idle seconds to add for this window'),
            'activeseconds' => new external_value(PARAM_INT, 'Active seconds in this window', VALUE_DEFAULT, 0),
            'keystrokes' => new external_value(PARAM_INT, 'Keystroke count (no key contents)', VALUE_DEFAULT, 0),
            'clicks' => new external_value(PARAM_INT, 'Click / mousedown count', VALUE_DEFAULT, 0),
            'mousemoves' => new external_value(PARAM_INT, 'Throttled mousemove count', VALUE_DEFAULT, 0),
            'scrolls' => new external_value(PARAM_INT, 'Scroll event count', VALUE_DEFAULT, 0),
            'touches' => new external_value(PARAM_INT, 'Touch start count', VALUE_DEFAULT, 0),
            'windowseconds' => new external_value(PARAM_INT, 'Length of the autosave window in seconds', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Return structure for save_heartbeat.
     *
     * @return external_single_structure
     */
    public static function save_heartbeat_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'idleseconds' => new external_value(PARAM_INT, 'Idle seconds accepted'),
            'activeseconds' => new external_value(PARAM_INT, 'Active seconds reported'),
        ]);
    }
}
