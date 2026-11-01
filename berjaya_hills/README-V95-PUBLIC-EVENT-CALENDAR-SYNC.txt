V95 - Public Event Calendar Sync

Updated public/calendar/index.php so the public Event Calendar includes all confirmed and completed event records regardless of whether their start date is in the past or future, and regardless of the is_public flag. The existing All / Upcoming / Past filters and archive-year navigation continue to separate dates for visitors.

Pending submissions remain visible only to their submitting owner; drafts, rejected and cancelled records are not exposed publicly. This keeps approval workflow/private booking details protected.

No database migration required.
