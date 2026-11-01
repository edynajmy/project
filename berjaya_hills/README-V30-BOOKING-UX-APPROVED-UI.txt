V30 - BOOKING UX APPROVED UI

Base: V29 User Dashboard Text Fix

Changes:
- Removed duplicate horizontal booking stepper; vertical Booking steps remains the single progress navigation.
- Converted the 9-step booking form into a true step-by-step wizard. Only the active step is visible; future steps remain locked until reached.
- Added Previous Step / Next Step controls to each step.
- Kept all original booking form fields and backend submission flow.
- Booking venue selector now shows only venues with status=available AND bookable=1, preventing information-only attractions from being selected for event bookings.
- Venue card upgraded with larger real primary image, thumbnail gallery from venue_images, View Details and View Gallery links.
- Capacity now displays the database value; if capacity is 0/unavailable it shows a clear unavailable message rather than claiming 0 guests as a valid capacity.
- Added client-side end-date validation so End Date cannot precede Start Date.
- Added date selection availability indicator; final availability remains confirmed server-side on submission.
- Booking summary made more compact with image, venue, metadata, divider, services and total cost.
- Hero now uses a venue image background with a dark overlay for better visual hierarchy.
- Existing pricing calculations, timeline compilation, draft/save/submit actions and CSRF protection are preserved.

Database migration: none required.
