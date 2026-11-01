-- v119: Make custom "Others" venue names available everywhere venue details are shown.
-- Run after v118_booking_other_venue.sql.

CREATE OR REPLACE VIEW vw_calendar_events AS
SELECT
  e.id,
  e.title,
  e.start_at,
  e.end_at,
  e.status,
  e.is_public,
  c.name AS category_name,
  c.color_hex,
  e.other_venue_name,
  GROUP_CONCAT(DISTINCT v.name ORDER BY v.name SEPARATOR ', ') AS venue_names
FROM events e
LEFT JOIN event_categories c ON c.id=e.category_id
LEFT JOIN event_venues ev ON ev.event_id=e.id
LEFT JOIN venues v ON v.id=ev.venue_id
GROUP BY e.id,e.title,e.start_at,e.end_at,e.status,e.is_public,c.name,c.color_hex,e.other_venue_name;

CREATE OR REPLACE VIEW vw_upcoming_public_events AS
SELECT
  e.id,
  e.title,
  e.start_at,
  e.end_at,
  c.name AS category_name,
  c.color_hex,
  e.other_venue_name,
  GROUP_CONCAT(DISTINCT v.name ORDER BY v.name SEPARATOR ', ') AS venue_names
FROM events e
LEFT JOIN event_categories c ON c.id=e.category_id
LEFT JOIN event_venues ev ON ev.event_id=e.id
LEFT JOIN venues v ON v.id=ev.venue_id
WHERE e.is_public=1
  AND e.status='confirmed'
  AND e.start_at>=NOW()
GROUP BY e.id,e.title,e.start_at,e.end_at,c.name,c.color_hex,e.other_venue_name;
