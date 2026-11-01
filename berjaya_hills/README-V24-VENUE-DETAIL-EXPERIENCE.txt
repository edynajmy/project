BERJAYA HILLS — V24 VENUE DETAIL EXPERIENCE

Base: v23 ADMIN VENUE PHOTO MANAGEMENT

Updated public/venues/view.php with a single-page venue experience:
- Multi-image hero slider using venue_images / primary image fallback
- Full-width visual storytelling layout instead of the previous two-column boxes
- Facilities displayed as icon cards
- Venue-specific activity cards for the current project places with supported activity content
- Reviews & ratings display backed by optional venue_reviews table
- Google Maps embed + Open in Google Maps link
- Friendly Information Only banner for non-bookable places
- Nearby places from the existing Venues catalogue
- Responsive mobile/tablet/desktop styling

Database:
Run database/add_venue_reviews.sql if you want the reviews table enabled. Until then the page stays safe and shows a setup message instead of failing.

Important content note:
No fabricated visitor reviews or ratings are inserted. The review section only displays approved rows that exist in venue_reviews.
