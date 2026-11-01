Berjaya Hills Event Calendar - v83

Fixes:
- Synchronizes the selected-day panel with the month currently being browsed.
- Month previous/next navigation now updates the selected date together with month/year.
- Changing archive year resets to January 1 of the chosen year instead of retaining a stale selected date.
- Bumps the shared stylesheet cache key to v83 so browsers request the current CSS.

Event records remain sourced from the database. Past/upcoming visibility depends on saved event dates, public visibility, and confirmed/completed status; this package does not invent or seed event history.

Install: replace the project files with this package, then hard-refresh the browser (Ctrl+F5). If an old URL contains month/date parameters, open Event Calendar from the sidebar again.
