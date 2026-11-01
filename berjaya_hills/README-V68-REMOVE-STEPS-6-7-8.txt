V68 — REMOVE BOOKING STEPS 6, 7 AND 8

Based on v67 TIMELINE-CARD-COMPACT-UP.

Changes:
- Removed booking step 6: Timeline.
- Removed booking step 7: Additional Information.
- Removed booking step 8: Summary.
- Booking navigator now contains only 5 steps:
  1. Event Information
  2. Date & Time
  3. Venue
  4. Group & Rooms
  5. Facilities
- Step 5 now has the final "Submit Booking →" button together with Save Draft.
- Previous Step remains available on Step 5.
- Booking step JavaScript is limited to steps 1–5.
- Removed the Timeline button event listener so the removed step does not cause a JavaScript error.
- Existing booking save/venue/date/facilities functionality is retained.

Note:
- The hidden legacy booking summary panel remains in the source because the existing cost-summary JavaScript still references it; it is not a booking step and remains hidden by the existing CSS.
