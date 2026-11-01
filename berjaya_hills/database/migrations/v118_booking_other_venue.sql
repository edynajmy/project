-- V118: Allow a booking to specify a venue/space not yet listed in the venue catalogue.
ALTER TABLE events ADD COLUMN other_venue_name VARCHAR(150) NULL AFTER description;

CREATE OR REPLACE VIEW vw_booking_summary AS SELECT b.id,b.booking_ref,b.status,b.total_estimate,b.created_at,b.submitted_at,b.event_id,u.id user_id,u.full_name user_name,u.email,e.title event_title,e.start_at,e.end_at,e.expected_pax,e.other_venue_name,GROUP_CONCAT(v.name SEPARATOR ', ') venue_names FROM bookings b JOIN users u ON u.id=b.user_id JOIN events e ON e.id=b.event_id LEFT JOIN event_venues ev ON ev.event_id=e.id LEFT JOIN venues v ON v.id=ev.venue_id GROUP BY b.id;
