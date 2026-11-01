V47 – RESET NEW BOOKING

Purpose
- Remove the current test/demo booking BHR-260930-5C7B1D.
- Make New Booking start genuinely blank when no draft_id is supplied.
- Do not auto-select the first venue or show its image in a brand-new booking.

Database reset
Run database/reset_current_test_booking.sql once in the project database.
It deletes only BHR-260930-5C7B1D; linked child records are removed by the
existing ON DELETE CASCADE relationships.

Existing drafts
An existing draft is still loaded only when the URL explicitly contains
?draft_id=... . This preserves the Save Draft / Continue Draft workflow.
