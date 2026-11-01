V89 - USER CALENDAR BOOKING VISIBILITY + DATE CHANGE REQUEST

Changes:
- User calendar now shows the signed-in user's submitted bookings in pending review, pending information, and confirmed states.
- Draft, rejected, and cancelled bookings are excluded.
- Calendar event chips show booking status and distinguish pending from confirmed visually.
- Eligible event chips can be dragged to another date. Dropping submits a date-change request while preserving the original time and event duration.
- The current booking date is not changed until Management approves the request through the existing booking change workflow.
- A second pending change request for the same booking is blocked.

No database migration required; uses existing booking_change_requests table.
