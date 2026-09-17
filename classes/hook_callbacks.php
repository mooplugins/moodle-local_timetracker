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
 * Hook callbacks for local_timetracker.
 *
 * @package    local_timetracker
 * @copyright  2026 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_timetracker;

use context_course;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../locallib.php');

/**
 * Output hook callbacks.
 */
class hook_callbacks {
    /**
     * Inject tracking UI and update timing logs before the page footer.
     *
     * @param \core\hook\output\before_footer_html_generation $hook
     * @return void
     */
    public static function before_footer_html_generation(
        \core\hook\output\before_footer_html_generation $hook
    ): void {
        global $PAGE, $DB;

        local_timetracker_ensure_schema();

        if (!get_config('local_timetracker', 'enabled')) {
            return;
        }

        $pagetype = explode('-', $PAGE->pagetype);
        $finishlastattempt = true;

        $iscmview = !empty($PAGE->cm->id)
            && (!isset($pagetype[2]) || $pagetype[2] !== 'mod')
            && !has_capability('moodle/course:update', context_course::instance($PAGE->cm->course));

        if ($iscmview) {
            $coursemoduleid = $PAGE->cm->id;
            $courseid = $PAGE->cm->course;
            $skip = self::should_skip_module_page($PAGE);

            $timetracker = $DB->get_record(LOCAL_TIMETRACKER_TABLE, [
                'course' => $courseid,
                'coursemodule' => $coursemoduleid,
                'enabled' => 1,
            ]);

            if ($timetracker && !$skip) {
                $finishlastattempt = false;

                $lastopenattempt = timetracker_get_last_session_open_attempt();
                if ($lastopenattempt && (int) $lastopenattempt->timetrackerid !== (int) $timetracker->id) {
                    timetracker_finish_attempt(
                        $lastopenattempt->userid,
                        $lastopenattempt->timetrackerid,
                        $lastopenattempt->sessionid
                    );
                }

                timetracker_log($timetracker);

                $url = (new moodle_url($PAGE->url))->out(false);
                $idletext = get_string('idletext', 'local_timetracker');
                $buttonlabel = get_string('button_resume', 'local_timetracker');

                $html = '<div id="timetracker_idle" class="local-timetracker-idle" style="display:none;">'
                    . '<div class="local-timetracker-idle-dialog">'
                    . $idletext
                    . ' <span id="timetracker_idle_time_show"></span> minutes.'
                    . '<br /><br />'
                    . '<form action="' . s($url) . '" method="post">'
                    . '<input type="hidden" id="timetracker_idle_time" name="timetracker_idle_time" value="">'
                    . '<input type="submit" value="' . s($buttonlabel) . '" class="btn btn-primary">'
                    . '</form>'
                    . '</div></div>';

                $hook->add_html($html);

                $activitypercent = get_config('local_timetracker', 'activitypercent');
                if ($activitypercent === false || $activitypercent === null || $activitypercent === '') {
                    $activitypercent = 20;
                }

                $PAGE->requires->js_call_amd('local_timetracker/tracker', 'init', [
                    (int) $timetracker->id,
                    (int) $coursemoduleid,
                    (int) $courseid,
                    (int) get_config('local_timetracker', 'autosavetime') * 60,
                    (int) $timetracker->idletime * 60,
                    (int) $timetracker->idletimeneglect * 60,
                    (int) $activitypercent,
                ]);
            }
        }

        if ($finishlastattempt) {
            $attempts = timetracker_get_session_open_attempts();
            foreach ($attempts as $attempt) {
                timetracker_finish_attempt($attempt->userid, $attempt->timetrackerid, $attempt->sessionid);
            }
        }
    }

    /**
     * Whether tracking should be skipped for the current module page.
     *
     * @param \moodle_page $page
     * @return bool
     */
    private static function should_skip_module_page($page): bool {
        $modname = $page->cm->modname ?? '';

        if ($modname === 'assign') {
            $action = optional_param('action', '', PARAM_ALPHA);
            return $action !== 'editsubmission';
        }

        if ($modname === 'quiz') {
            $script = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
            return !in_array($script, ['attempt.php', 'summary.php'], true);
        }

        if ($modname === 'scorm') {
            $script = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
            return $script !== 'player.php';
        }

        return false;
    }
}
