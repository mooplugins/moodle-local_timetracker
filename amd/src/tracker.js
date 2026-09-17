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
 * Tracker management code — activity signals + active/idle heartbeat autosave.
 *
 * @module     local_timetracker/tracker
 * @copyright  2026 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax'], function(ajax) {
    var tracker = {
        trackerId: null,
        courseModuleId: null,
        courseId: null,
        autoSaveTime: null,
        idleTimeMax: null,
        idleTimeNeglect: null,
        activityPercent: 20,

        /** Seconds since last learner signal (mouse/key/touch/scroll). */
        secondsSinceActivity: 0,
        /** Seconds elapsed in the current autosave window. */
        windowSeconds: 0,
        /** Seconds in the current window counted as active (within grace). */
        windowActiveSeconds: 0,
        /** Idle seconds pending for the overlay UI / form post. */
        overlayIdleSeconds: 0,
        overlayStarted: false,

        /** Activity signal counts for the current autosave window (not key contents). */
        keystrokes: 0,
        clicks: 0,
        mousemoves: 0,
        scrolls: 0,
        touches: 0,

        /** True when any signal fired during the current one-second tick. */
        tickHadSignal: false,
        saving: false,

        /**
         * Initialise the tracker.
         *
         * @param {Number} trackerId
         * @param {Number} courseModuleId
         * @param {Number} courseId
         * @param {Number} autoSaveTime Autosave interval in seconds.
         * @param {Number} idleTimeMax Max idle seconds before overlay.
         * @param {Number} idleTimeNeglect Allowed idle (grace) seconds still counted as learning.
         * @param {Number} activityPercent Min % of window that must be active to keep active seconds.
         */
        init: function(trackerId, courseModuleId, courseId, autoSaveTime, idleTimeMax, idleTimeNeglect, activityPercent) {
            tracker.trackerId = trackerId;
            tracker.courseModuleId = courseModuleId;
            tracker.courseId = courseId;
            tracker.autoSaveTime = Math.max(1, parseInt(autoSaveTime, 10) || 120);
            tracker.idleTimeMax = Math.max(1, parseInt(idleTimeMax, 10) || 480);
            // Allowed idle (grace) must be shorter than the overlay threshold.
            tracker.idleTimeNeglect = Math.max(0, parseInt(idleTimeNeglect, 10) || 240);
            if (tracker.idleTimeNeglect >= tracker.idleTimeMax) {
                tracker.idleTimeNeglect = Math.max(0, tracker.idleTimeMax - 1);
            }
            tracker.activityPercent = Math.min(100, Math.max(0, parseInt(activityPercent, 10) || 20));

            tracker.secondsSinceActivity = 0;
            tracker.windowSeconds = 0;
            tracker.windowActiveSeconds = 0;
            tracker.overlayIdleSeconds = 0;
            tracker.overlayStarted = false;
            tracker.resetSignalCounts();
            tracker.tickHadSignal = false;
            tracker.saving = false;

            tracker.bindActivityListeners();
            tracker.bindResumeHandler();
            tracker.startTicker();

            // Flush pending heartbeat when leaving the page.
            window.addEventListener('pagehide', function() {
                tracker.flushHeartbeat(true);
            });
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    tracker.flushHeartbeat(false);
                }
            });
        },

        /**
         * Reset per-window signal counters.
         */
        resetSignalCounts: function() {
            tracker.keystrokes = 0;
            tracker.clicks = 0;
            tracker.mousemoves = 0;
            tracker.scrolls = 0;
            tracker.touches = 0;
        },

        /**
         * Whether the idle overlay is currently visible.
         *
         * @return {Boolean}
         */
        isOverlayVisible: function() {
            var overlay = document.getElementById('timetracker_idle');
            return !!(overlay && overlay.style.display === 'block');
        },

        /**
         * Record a learner signal (counts only — no key contents / coordinates stored).
         *
         * @param {String} type
         */
        noteSignal: function(type) {
            // Ignore input while the idle dialog is up (except resume, handled separately).
            if (tracker.isOverlayVisible()) {
                return;
            }

            tracker.tickHadSignal = true;
            tracker.secondsSinceActivity = 0;

            if (type === 'keydown') {
                tracker.keystrokes++;
            } else if (type === 'click' || type === 'mousedown') {
                tracker.clicks++;
            } else if (type === 'mousemove') {
                tracker.mousemoves++;
            } else if (type === 'scroll') {
                tracker.scrolls++;
            } else if (type === 'touchstart') {
                tracker.touches++;
            }
        },

        /**
         * Bind browser activity listeners.
         */
        bindActivityListeners: function() {
            var mousemoveThrottle = 0;
            document.addEventListener('keydown', function() {
                tracker.noteSignal('keydown');
            }, {passive: true});
            document.addEventListener('mousedown', function() {
                tracker.noteSignal('mousedown');
            }, {passive: true});
            document.addEventListener('click', function() {
                tracker.noteSignal('click');
            }, {passive: true});
            document.addEventListener('touchstart', function() {
                tracker.noteSignal('touchstart');
            }, {passive: true});
            document.addEventListener('scroll', function() {
                tracker.noteSignal('scroll');
            }, {passive: true});
            document.addEventListener('mousemove', function() {
                // Do not treat mouse motion toward Resume as learning activity.
                if (tracker.isOverlayVisible()) {
                    return;
                }
                var now = Date.now();
                // Throttle mousemove counting so continuous motion does not flood counts.
                if (now - mousemoveThrottle < 250) {
                    tracker.tickHadSignal = true;
                    tracker.secondsSinceActivity = 0;
                    return;
                }
                mousemoveThrottle = now;
                tracker.noteSignal('mousemove');
            }, {passive: true});
        },

        /**
         * Whether the learner is still within the allowed-idle grace window.
         *
         * @return {Boolean}
         */
        isWithinGrace: function() {
            if (document.hidden) {
                return false;
            }
            // Once the idle overlay is showing, time is idle until the learner resumes.
            if (tracker.overlayStarted || tracker.isOverlayVisible()) {
                return false;
            }
            return tracker.secondsSinceActivity <= tracker.idleTimeNeglect;
        },

        /**
         * Dismiss the idle overlay after Resume. Heartbeats already stored idle
         * seconds, so do not POST overlay idle again (that would double-count).
         */
        resumeFromIdle: function() {
            var overlay = document.getElementById('timetracker_idle');
            var idleInput = document.getElementById('timetracker_idle_time');
            if (overlay) {
                overlay.style.display = 'none';
            }
            if (idleInput) {
                idleInput.value = '0';
            }
            tracker.overlayStarted = false;
            tracker.overlayIdleSeconds = 0;
            tracker.secondsSinceActivity = 0;
            tracker.tickHadSignal = true;
            // Persist any open window (mostly idle) then continue tracking.
            tracker.flushHeartbeat(false);
        },

        /**
         * Bind the Resume button so it does not re-submit idle via form POST.
         */
        bindResumeHandler: function() {
            var form = document.querySelector('#timetracker_idle form');
            if (!form || form.getAttribute('data-tt-bound')) {
                return;
            }
            form.setAttribute('data-tt-bound', '1');
            form.addEventListener('submit', function(event) {
                event.preventDefault();
                tracker.resumeFromIdle();
            });
        },

        /**
         * Show or update the idle overlay.
         */
        updateOverlay: function() {
            var overlay = document.getElementById('timetracker_idle');
            var showNode = document.getElementById('timetracker_idle_time_show');
            var idleInput = document.getElementById('timetracker_idle_time');
            if (!overlay) {
                return;
            }

            if (!tracker.overlayStarted && tracker.secondsSinceActivity < tracker.idleTimeMax) {
                overlay.style.display = 'none';
                tracker.overlayIdleSeconds = 0;
                return;
            }

            if (!tracker.overlayStarted) {
                tracker.overlayStarted = true;
                tracker.overlayIdleSeconds = Math.max(0, tracker.secondsSinceActivity - tracker.idleTimeNeglect);
            } else {
                tracker.overlayIdleSeconds++;
            }

            overlay.style.display = 'block';

            if (showNode) {
                var show = Math.max(0, tracker.overlayIdleSeconds);
                var minutesIdle = parseInt(show / 60, 10);
                var secondsIdle = parseInt(show % 60, 10);
                minutesIdle = minutesIdle < 10 ? '0' + minutesIdle : String(minutesIdle);
                secondsIdle = secondsIdle < 10 ? '0' + secondsIdle : String(secondsIdle);
                showNode.textContent = minutesIdle + ':' + secondsIdle;
            }
            if (idleInput) {
                idleInput.value = tracker.overlayIdleSeconds;
            }
        },

        /**
         * Decide how many idle seconds to persist for this autosave window.
         *
         * @return {Number}
         */
        idleSecondsForWindow: function() {
            var total = tracker.windowSeconds;
            if (total <= 0) {
                return 0;
            }
            var active = tracker.windowActiveSeconds;
            var engagement = (active / total) * 100;
            if (engagement >= tracker.activityPercent) {
                return Math.max(0, total - active);
            }
            // Below engagement threshold: treat the whole window as idle.
            return total;
        },

        /**
         * One-second ticker: classify active/idle, overlay, and periodic heartbeat.
         */
        startTicker: function() {
            window.setInterval(function() {
                var hadSignal = tracker.tickHadSignal;
                tracker.tickHadSignal = false;

                if (document.hidden) {
                    tracker.secondsSinceActivity++;
                } else if (hadSignal) {
                    tracker.secondsSinceActivity = 0;
                } else {
                    tracker.secondsSinceActivity++;
                }

                tracker.windowSeconds++;
                if (tracker.isWithinGrace()) {
                    tracker.windowActiveSeconds++;
                }

                tracker.updateOverlay();

                if (tracker.windowSeconds >= tracker.autoSaveTime) {
                    tracker.flushHeartbeat(false);
                }
            }, 1000);
        },

        /**
         * Persist end.time + idle for the current window via AJAX heartbeat.
         *
         * @param {Boolean} sync Prefer keepalive-style send (pagehide).
         */
        flushHeartbeat: function(sync) {
            if (tracker.windowSeconds <= 0 || tracker.saving) {
                return;
            }

            var idleSeconds = tracker.idleSecondsForWindow();
            var payload = {
                trackerid: tracker.trackerId,
                idleseconds: idleSeconds,
                activeseconds: Math.max(0, tracker.windowSeconds - idleSeconds),
                keystrokes: tracker.keystrokes,
                clicks: tracker.clicks,
                mousemoves: tracker.mousemoves,
                scrolls: tracker.scrolls,
                touches: tracker.touches,
                windowseconds: tracker.windowSeconds
            };

            // Reset window before the request so ticks during flight start a new window.
            tracker.windowSeconds = 0;
            tracker.windowActiveSeconds = 0;
            tracker.resetSignalCounts();

            tracker.saving = true;
            ajax.call([{
                methodname: 'local_timetracker_save_heartbeat',
                args: payload,
                done: function() {
                    tracker.saving = false;
                },
                fail: function() {
                    tracker.saving = false;
                }
            }]);

            // Sync flag reserved for future navigator.sendBeacon fallback.
            void sync;
        }
    };

    return {
        init: tracker.init
    };
});
