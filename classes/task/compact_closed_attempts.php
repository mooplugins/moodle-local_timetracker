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
 * Scheduled task to compact closed attempts into the report table.
 *
 * @package    local_timetracker
 * @copyright  2026 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_timetracker\task;

/**
 * Move finished log attempts into local_timetracker_report and delete those logs.
 */
class compact_closed_attempts extends \core\task\scheduled_task {
    /**
     * Task name shown to admins.
     *
     * @return string
     */
    public function get_name() {
        return get_string('compact_closed_attempts', 'local_timetracker');
    }

    /**
     * Compact closed attempts.
     *
     * @return void
     */
    public function execute() {
        global $CFG;
        require_once($CFG->dirroot . '/local/timetracker/locallib.php');

        $compacted = local_timetracker_compact_closed_attempts(500);
        mtrace('local_timetracker: compacted ' . $compacted . ' closed attempt(s) into report table.');
    }
}
