V54 — BOOKING WORKSPACE APPROVED UI

Base:
V53 BOOKING WORKSPACE HARD FIX

Applied to user/bookings/create.php:
- Rebuilt booking workspace to match approved 3-column reference UI.
- Compact global user sidebar (250px).
- Booking steps sidebar fixed as left column.
- Active booking form remains in the center column.
- Booking Summary stays in the right column on desktop and is sticky.
- Removed the large booking hero from the workspace so the form starts immediately below the header.
- Removed the old conflicting booking grid behavior by adding one final authoritative layout layer.
- Responsive behavior stacks the workspace only on genuinely narrow screens.
- Step 1 now includes Event Description / Purpose (optional), saved through the existing description field.
- Step 1 desktop field arrangement matches the reference: Event Name / Category / Expected Pax; Organizer / Company; Contact / Email / Phone.
- Added live character counter for Event Description / Purpose.

PHP syntax checked successfully with php -l.
