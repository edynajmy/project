V51 - Final Booking Workspace Responsive Fix

Base: V50 Booking Summary Right

Fix:
- Keeps Booking Steps, active booking form, and Booking Summary in one 3-column row from 701px CSS viewport and above.
- Prevents the Booking Summary from dropping into a second row at common browser zoom levels.
- Keeps the approved desktop UI structure: left steps / center form / right summary.
- Only stacks the three sections on genuinely narrow mobile viewports (<=700px).
- Uses compact column sizing between 701px and 1200px so the three columns remain visible.
- No database or booking data changes.

Validation:
- user/bookings/create.php passed php -l.
