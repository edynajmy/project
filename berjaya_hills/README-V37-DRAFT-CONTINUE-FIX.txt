V37 – Draft Continue / Resume Fix

Fixes:
- Continue booking from a saved draft now opens create.php with draft_id.
- Saved draft values are loaded back into the booking form: event title, category, pax, dates/times, venue, rooms, facilities, food and notes.
- Saved timeline items are restored.
- Saving an existing draft updates the existing event/booking instead of creating a new booking.
- Existing draft line items are replaced with the latest form selections to prevent duplicates.
- Continue booking no longer resets the form to a blank new booking.
- Existing V36 premium booking-details UI is preserved.

Validation:
- PHP syntax checked for create.php, save.php and view.php.
