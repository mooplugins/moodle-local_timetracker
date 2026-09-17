# Time Tracker for Moodle

Track how long learners spend on selected activities and resources — with idle detection so quiet time is not counted as study time.

## Why Time Tracker?

Moodle knows when a learner opens an activity, but it does not measure engaged time on that activity out of the box. Teachers often need a clearer answer: *How long has this learner actually spent on this resource?*

Time Tracker fills that gap. When enabled on a course module, it records timed attempts for learners, subtracts idle periods, and exposes totals that other plugins can use (for example availability restrictions that unlock content after enough tracked time).

## Features

### Per-activity tracking

Enable Time Tracker on supported activities and resources from the course module settings. Defaults (allowed module types and idle thresholds) are controlled site-wide; each activity can override idle settings.

### Course report for staff

Managers and users with permission can open **Site administration → Reports → Time Tracker report**, pick a course, and see enrolled users with:

- Total time tracked
- How many activities have tracked time
- When tracking last ended

Search, browse pages of results, and export to CSV or Excel.

### Idle-aware timing

While a learner is on a tracked page, Time Tracker:

- Records start and end timestamps for the current Moodle session attempt
- Listens for key, mouse, scroll, and touch signals (counts only) to judge activity
- Heartbeat-autosaves active/idle time on every autosave interval (does not wait for the overlay)
- Uses **Minimum activity percent** so low-engagement windows can be saved as idle
- Shows an idle overlay after a configurable period of inactivity
- Subtracts idle seconds from totals (with an optional “allowed idle” grace still counted as learning time)

Teachers and managers with course update permission are not tracked.

### Session cleanup

Leaving a tracked activity closes open attempts for that browser session. A scheduled task also finishes attempts that have been idle longer than the site timeout.

### Privacy-aware

Stored configuration, open timing logs, and compacted report totals are covered by Moodle’s Privacy API, so sites can include them in privacy exports and deletion where required.

### Reporting-ready totals

Closed attempts are compacted by a scheduled task into `local_timetracker_report`, keeping the hot log table small while reports and availability checks stay fast.

### Ready for other plugins

Totals can be used by companion plugins such as the **Time tracked** availability condition
(`availability_timetracked`) and the **Time Tracker** block (`block_timetracker`).

## Requirements

- Moodle 4.5 or later (CI tested on 4.5, 5.0, and 5.2)
- JavaScript enabled in the learner’s browser
- MariaDB, MySQL, or PostgreSQL

## Installation

1. Copy this plugin into `local/timetracker` in your Moodle codebase.
2. Visit **Site administration → Notifications** and complete the installation.
3. Configure defaults at **Site administration → Plugins → Local plugins → Time Tracker**.
4. Edit a supported activity and enable **Time Tracker** in the activity settings.
5. Open the report from **Site administration → Reports → Time Tracker report**.

## Configuration

| Setting | Description |
|---------|-------------|
| Enable Time Tracker | Global on/off switch |
| Idle session timeout | Minutes before cron finishes an inactive attempt |
| Auto save time | Minutes between active/idle heartbeat autosaves |
| Minimum activity percent | Min % of an autosave window that must be active; below this the window is saved as idle |
| Activities that can use Time Tracker | All activities/resources, or only the listed module types |
| Allowed activities/resources | Comma-separated module names when using the list scope |
| Maximum idle time | Default minutes before the idle overlay appears |
| Allowed idle time | Minutes still counted when the idle overlay appears |

## Privacy

The plugin implements the Moodle Privacy API. It stores:

- Per-activity tracking configuration (`local_timetracker`)
- Per-user open/recent session timing logs (`local_timetracker_log`)
- Compacted closed-attempt totals (`local_timetracker_report`)

Export and deletion are supported for affected course contexts.

## Changelog

See [CHANGES.md](CHANGES.md).

## License

GNU GPL v3 or later. See [LICENSE](LICENSE).

## Credits

Originally developed for ScholarLMS. Maintained by [MooPlugins](https://www.mooplugins.com/).
