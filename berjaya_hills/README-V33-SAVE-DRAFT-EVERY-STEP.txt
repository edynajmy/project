V33 – SAVE DRAFT ON EVERY BOOKING STEP

Base: V32 Booking Blank Button Fix.

Changes:
- Added a visible Save Draft button to Steps 1–9 of the guided booking form.
- Save Draft uses a dedicated button handler so hidden future required fields do not trigger browser validation.
- Before saving, the system checks the core booking details required by the existing save.php workflow: Event Name, Event Category, Start Date, End Date and Venue.
- Date validation and timeline compilation run before the draft is submitted.
- Review & Submit remains available only on the final Summary step.
- Previous/Next navigation remains unchanged.

Note: The existing database schema requires event start/end and a venue for a persisted booking record, so Save Draft still asks for those core details before creating the draft record. Later-step optional fields can remain incomplete.
