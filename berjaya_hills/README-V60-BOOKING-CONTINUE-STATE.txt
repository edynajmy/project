Berjaya Hills v60 – Booking Continue State

Changes:
- The user venue detail breadcrumb now returns to the previous booking page in the same tab when possible, with a booking-step fallback URL.
- New Booking stores entered form values and current wizard step in sessionStorage while the user opens venue details/gallery.
- Returning to New Booking restores the in-progress values and active step for up to 8 hours in the same browser tab.
- PHP syntax checked for public/venues/view.php and user/bookings/create.php.

Important: sessionStorage is tab-scoped. Keep the venue detail page and booking in the same tab. This is browser-side temporary progress, not a permanent saved draft; use Save Draft for durable storage.
