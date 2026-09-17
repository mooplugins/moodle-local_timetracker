# Changelog

All notable changes to the Time Tracker plugin are documented here.

## 1.4.3 - 2026-09-09

### Fixed

- Idle overlay time is no longer counted as active: mouse movement toward
  **Resume** no longer resets the activity timer, grace stops while the overlay
  is visible, and Resume no longer re-posts idle seconds already saved by heartbeats.
- Allowed idle is clamped so it cannot be greater than or equal to maximum idle.

## 1.4.2 - 2026-09-08

### Fixed

- Report totals no longer drop to zero after leaving an activity: finished log
  attempts are counted immediately, and closing an attempt compacts it into the
  report table without waiting for the scheduled task.

## 1.4.1 - 2026-09-08

### Changed

- Report page pager JS supports button-style pagination (`aria-disabled` / `disabled`).
- Report page layout and styles refined for the staff report UI.

## 1.4.0 - 2026-09-08

### Added

- Site report under **Site administration → Reports → Time Tracker report**.
- Capability `local/timetracker:viewreport` (managers and course creators by default).
- Course picker, search, pagination, and CSV/Excel export for enrolled users’
  tracked totals (report table + open logs).

## 1.3.1 - 2026-09-08

### Added

- Setting **Activities that can use Time Tracker**: track on **all** activities/resources,
  or only the comma-separated module types list (previous behaviour).

## 1.3.0 - 2026-09-08

### Added

- Heartbeat autosave (`local_timetracker_save_heartbeat`) that advances `end.time`
  and saves idle seconds on every autosave interval — not only when the idle overlay shows.
- Browser activity signals: keydown, click/mousedown, throttled mousemove, scroll, touch
  (counts only; no key contents or coordinates stored).
- Site setting **Minimum activity percent** — if engagement in an autosave window is
  below this percent, the whole window is saved as idle.
- Allowed idle (`idletimeneglect`) used as grace: seconds within grace after the last
  signal still count as active learning time.

### Changed

- Autosave description clarified for active/idle heartbeat behaviour.
- Idle overlay still appears after maximum idle time; heartbeats continue underneath.

## 1.2.0 - 2026-09-08

### Added

- `local_timetracker_report` table for compacted closed-attempt totals.
- Scheduled task `compact_closed_attempts` (every 10 minutes) that moves finished
  log attempts into the report table and deletes those log rows.
- Public helpers `local_timetracker_get_timespent()` / report + open-log readers
  so reporting no longer depends on heavy joins over historical logs.

### Changed

- Time totals (API, availability condition, course statistics, block) now use
  compacted report rows plus any still-open log attempts.
- Privacy API covers the new report table (export and deletion).

## 1.1.0 - 2026-09-07

### Added

- Privacy API provider for per-activity configuration and per-user timing logs.
- GitHub Actions Moodle Plugin CI workflow (Moodle 4.5, 5.0, and 5.2).
- `LICENSE`, `CHANGES.md`, and `thirdpartylibs.xml`.
- `$plugin->supported` metadata for Moodle 4.5–5.2.

### Changed

- Renamed database tables to `local_timetracker` and `local_timetracker_log`
  (with upgrade rename from legacy `timetracker` / `timetracker_log` names).
- Tracking entry point uses the `before_footer_html_generation` hook only
  (avoids double-tracking on modern Moodle).
- Parameterised SQL and session handling for safer logging.
- Requires Moodle 4.5 or later.
- Removed “(beta)” from the plugin display name.

### Fixed

- Hard-coded `mdl_` table prefix in course time-spent helper.
- Idle timer now resets on learner activity and treats a hidden tab as idle.
