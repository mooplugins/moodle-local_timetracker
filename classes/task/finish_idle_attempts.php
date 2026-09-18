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
 * Scheduled task to close idle Time Tracker attempts.
 *
 * @package    local_timetracker
 * @author     BitKea Technologies LLP
 * @copyright  2026 BitKea Technologies LLP
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_timetracker\task;

/**
 * Finish attempts whose end.time is older than the configured timeout.
 */
class finish_idle_attempts extends \core\task\scheduled_task {
    /**
     * Task name shown to admins.
     *
     * @return string
     */
    public function get_name() {
        return get_string('finish_idle_attempts', 'local_timetracker');
    }

    /**
     * Close timed-out open attempts.
     *
     * @return void
     */
    public function execute() {
        global $CFG;
        require_once($CFG->dirroot . '/local/timetracker/locallib.php');

        $finishattempttime = (int) get_config('local_timetracker', 'finishattempttime');
        if ($finishattempttime <= 0) {
            return;
        }

        $attempts = timetracker_get_open_attempts();
        foreach ($attempts as $attempt) {
            $minutespassed = (time() - (int) $attempt->value) / 60;
            if ($minutespassed > $finishattempttime) {
                timetracker_finish_attempt($attempt->userid, $attempt->timetrackerid, $attempt->sessionid);
            }
        }
    }
}
