USE berjaya_hills;

-- Unified Venues catalogue migration.
-- Adds every requested Berjaya Hills place to the existing venues catalogue.
-- Event venues remain bookable; resort/attraction places are information-only.

ALTER TABLE venues ADD COLUMN IF NOT EXISTS bookable TINYINT(1) NOT NULL DEFAULT 1 AFTER featured;

-- Ensure every requested place exists in locations.
INSERT IGNORE INTO locations(category_id,name,slug,location_type,description,is_featured)
SELECT id,'Colmar Tropicale Resort','colmar-tropicale-resort','Accommodation','French-themed resort village and accommodation destination in Berjaya Hills.',1 FROM location_categories WHERE slug='accommodation';
INSERT IGNORE INTO locations(category_id,name,slug,location_type,description,is_featured)
SELECT id,'The Chateau Spa & Wellness Resort','the-chateau-spa-wellness-resort','Accommodation','European-inspired spa and wellness resort set within Berjaya Hills.',1 FROM location_categories WHERE slug='accommodation';
INSERT IGNORE INTO locations(category_id,name,slug,location_type,description,is_featured)
SELECT id,'Berjaya Hills Golf & Country Club','berjaya-hills-golf-country-club','Activity','Scenic golf course and country club within Berjaya Hills.',1 FROM location_categories WHERE slug='activities';
INSERT IGNORE INTO locations(category_id,name,slug,location_type,description,is_featured)
SELECT id,'Japanese Village','japanese-village','Activity','Serene Japanese-inspired cultural and garden attraction within Berjaya Hills.',1 FROM location_categories WHERE slug='activities';
INSERT IGNORE INTO locations(category_id,name,slug,location_type,description,is_featured)
SELECT id,'Rabbit Park','rabbit-park','Activity','Family-friendly animal attraction featuring rabbits and small animals.',0 FROM location_categories WHERE slug='activities';
INSERT IGNORE INTO locations(category_id,name,slug,location_type,description,is_featured)
SELECT id,'Berjaya Hills Adventure Park','berjaya-hills-adventure-park','Activity','Outdoor adventure destination with flying fox, climbing and rope activities.',1 FROM location_categories WHERE slug='activities';
INSERT IGNORE INTO locations(category_id,name,slug,location_type,description,is_featured)
SELECT id,'Colmar Tropicale Lookout Point Tower','colmar-tropicale-lookout-point-tower','Attraction','Scenic lookout point offering views across the Berjaya Hills landscape.',0 FROM location_categories WHERE slug='activities';
INSERT IGNORE INTO locations(category_id,name,slug,location_type,description,is_featured)
SELECT id,'Colmar Tropicale Convention Center','colmar-tropicale-convention-center','Convention Centre','Multi-purpose convention and meeting facility for conferences, functions and events.',1 FROM location_categories WHERE slug='facilities';
INSERT IGNORE INTO locations(category_id,name,slug,location_type,description,is_featured)
SELECT id,'Masjid Berjaya Hills Resort','masjid-berjaya-hills-resort','Religious Facility','Prayer facility serving guests and visitors at Berjaya Hills.',0 FROM location_categories WHERE slug='facilities';
INSERT IGNORE INTO locations(category_id,name,slug,location_type,description,is_featured)
SELECT id,'Botanical Garden','botanical-garden','Garden','Botanical garden within the Japanese Village area, surrounded by highland greenery.',1 FROM location_categories WHERE slug='activities';
INSERT IGNORE INTO locations(category_id,name,slug,location_type,description,is_featured)
SELECT id,'Berjaya Hills Horse Trails','berjaya-hills-horse-trails','Activity','Equestrian trail experience through the scenic forested surroundings of Berjaya Hills.',0 FROM location_categories WHERE slug='activities';
INSERT IGNORE INTO locations(category_id,name,slug,location_type,description,is_featured)
SELECT id,'Coniferous Forest','coniferous-forest','Nature','Highland forest attraction featuring cool-climate greenery and scenic nature surroundings.',0 FROM location_categories WHERE slug='activities';
INSERT IGNORE INTO locations(category_id,name,slug,location_type,description,is_featured)
SELECT id,'9D Motion Adventure','9d-motion-adventure','Activity','Immersive motion-based entertainment experience for visitors and families.',0 FROM location_categories WHERE slug='activities';

-- If the previous migration created a generic "The Chateau" venue, convert it to the requested full resort name.
UPDATE venues
SET name='The Chateau Spa & Wellness Resort', slug='the-chateau-spa-wellness-resort', venue_type='Accommodation', description='European-inspired spa and wellness resort set within Berjaya Hills.', bookable=0, capacity=0, area_sqft=0, rate=0
WHERE slug='the-chateau'
  AND NOT EXISTS (SELECT 1 FROM venues v2 WHERE v2.slug='the-chateau-spa-wellness-resort');

-- Add every requested resort/place to Venues.
INSERT INTO venues(location_id,name,slug,venue_type,description,capacity,area_sqft,rate,featured,bookable)
SELECT l.id,l.name,l.slug,COALESCE(l.location_type,'Place'),l.description,0,0,0,l.is_featured,0
FROM locations l
WHERE l.slug IN (
  'colmar-tropicale-resort',
  'the-chateau-spa-wellness-resort',
  'berjaya-hills-golf-country-club',
  'japanese-village',
  'rabbit-park',
  'berjaya-hills-adventure-park',
  'colmar-tropicale-lookout-point-tower',
  'colmar-tropicale-convention-center',
  'masjid-berjaya-hills-resort',
  'botanical-garden',
  'berjaya-hills-horse-trails',
  'coniferous-forest',
  '9d-motion-adventure'
)
AND NOT EXISTS (SELECT 1 FROM venues v WHERE v.slug=l.slug);

-- Keep the four event venues bookable.
UPDATE venues SET bookable=1 WHERE slug IN ('glass-hall','chateau-ballroom','conference-room-a','outdoor-garden');

-- All other requested places are information-only.
UPDATE venues SET bookable=0
WHERE slug IN (
  'colmar-tropicale-resort','the-chateau-spa-wellness-resort','berjaya-hills-golf-country-club',
  'japanese-village','rabbit-park','berjaya-hills-adventure-park',
  'colmar-tropicale-lookout-point-tower','colmar-tropicale-convention-center',
  'masjid-berjaya-hills-resort','botanical-garden','berjaya-hills-horse-trails',
  'coniferous-forest','9d-motion-adventure'
);
