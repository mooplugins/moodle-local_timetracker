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
 * External services for local_timetracker.
 *
 * @package    local_timetracker
 * @copyright  2026 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_timetracker_save_idle_time' => [
        'classname' => 'local_timetracker\\external\\tracker',
        'methodname' => 'save_idle_time',
        'description' => 'Save idle time due to inactivity (legacy overlay autosave)',
        'type' => 'write',
        'capabilities' => '',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_timetracker_save_heartbeat' => [
        'classname' => 'local_timetracker\\external\\tracker',
        'methodname' => 'save_heartbeat',
        'description' => 'Autosave active/idle heartbeat for the current Time Tracker attempt',
        'type' => 'write',
        'capabilities' => '',
        'ajax' => true,
        'loginrequired' => true,
    ],
];
