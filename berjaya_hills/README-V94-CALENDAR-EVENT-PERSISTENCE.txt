V94 — Calendar Event Persistence Fix

Fixed the Day view data range. The calendar's Day tab continues to render the month grid, so it now loads all events for the displayed month instead of querying only the selected date. Selecting another date will update the selected-day panel without removing other event cards from their calendar dates. Event records are not modified by this UI fix.

Updated: public/calendar/index.php
