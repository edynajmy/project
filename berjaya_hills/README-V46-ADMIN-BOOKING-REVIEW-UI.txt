V46 — Admin Booking Review UI

Based on V45 Food & Beverage Premium UI.

Changes:
- Admin booking review now shows complete booking data: owner, category, venue, participants, date/time, facilities, food & beverage, rooms and customer notes.
- Added Financial Summary with total estimate and 50% deposit calculation.
- Payment method displays the stored value when available; otherwise "Not specified". The booking schema now includes an optional payment_method field.
- Reject Booking now opens a reason modal and sends the reason in the notification.
- Added Hold / Need clarification workflow using new booking status pending_info, with a required admin note and customer notification.
- Added View User Profile quick action and a dedicated admin user profile page with booking history.
- Change Requests now show current vs requested date/time and venue where applicable.
- Added database migration: database/migrations/v46_admin_booking_review.sql
- Fresh schema updated for pending_info and payment_method.

IMPORTANT:
For an existing database, run database/migrations/v46_admin_booking_review.sql once before using Hold / Need clarification or the payment_method field.
