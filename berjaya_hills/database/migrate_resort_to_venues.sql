USE berjaya_hills;

-- Unified catalogue migration: move the public Resort Explorer places into Venues.
-- Existing venue records are preserved. Re-running this script is safe.

ALTER TABLE venues ADD COLUMN IF NOT EXISTS bookable TINYINT(1) NOT NULL DEFAULT 1 AFTER featured;

INSERT INTO venues(location_id,name,slug,venue_type,description,capacity,area_sqft,rate,featured,bookable)
SELECT l.id,l.name,l.slug,COALESCE(l.location_type,'Venue'),l.description,0,0,0,l.is_featured,0
FROM locations l
WHERE l.slug IN (
  'the-chateau','japanese-village','colmar-tropicale-resort','the-chateau-spa-wellness-resort',
  'berjaya-hills-golf-country-club','rabbit-park','berjaya-hills-adventure-park',
  'colmar-tropicale-lookout-point-tower','colmar-tropicale-convention-center',
  'masjid-berjaya-hills-resort','botanical-garden','berjaya-hills-horse-trails',
  'coniferous-forest','9d-motion-adventure'
)
AND NOT EXISTS (SELECT 1 FROM venues v WHERE v.slug=l.slug);

-- The four original event venues remain bookable.
UPDATE venues SET bookable=1 WHERE slug IN ('glass-hall','chateau-ballroom','conference-room-a','outdoor-garden');
