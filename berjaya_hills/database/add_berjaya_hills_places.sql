USE berjaya_hills;

-- Run this file on an existing installation to add the requested places.
-- The slugs are unique, so rerunning this file is safe.
INSERT IGNORE INTO locations(category_id,name,slug,location_type,description,is_featured)
SELECT id,'Colmar Tropicale Resort','colmar-tropicale-resort','Accommodation','French-themed resort village and accommodation destination in Berjaya Hills.',1 FROM location_categories WHERE slug='accommodation';
INSERT IGNORE INTO locations(category_id,name,slug,location_type,description,is_featured)
SELECT id,'The Chateau Spa & Wellness Resort','the-chateau-spa-wellness-resort','Accommodation','European-inspired wellness resort set within the Berjaya Hills landscape.',1 FROM location_categories WHERE slug='accommodation';
INSERT IGNORE INTO locations(category_id,name,slug,location_type,description,is_featured)
SELECT id,'Berjaya Hills Golf & Country Club','berjaya-hills-golf-country-club','Activity','Scenic 18-hole golf course and country club within Berjaya Hills.',1 FROM location_categories WHERE slug='activities';
INSERT IGNORE INTO locations(category_id,name,slug,location_type,description,is_featured)
SELECT id,'Rabbit Park','rabbit-park','Activity','Family-friendly animal attraction where visitors can interact with rabbits and other small animals.',0 FROM location_categories WHERE slug='activities';
INSERT IGNORE INTO locations(category_id,name,slug,location_type,description,is_featured)
SELECT id,'Berjaya Hills Adventure Park','berjaya-hills-adventure-park','Activity','Outdoor adventure destination offering activities such as flying fox, climbing and rope challenges.',1 FROM location_categories WHERE slug='activities';
INSERT IGNORE INTO locations(category_id,name,slug,location_type,description,is_featured)
SELECT id,'Colmar Tropicale Lookout Point Tower','colmar-tropicale-lookout-point-tower','Attraction','Scenic lookout point for views across the surrounding Berjaya Hills landscape.',0 FROM location_categories WHERE slug='activities';
INSERT IGNORE INTO locations(category_id,name,slug,location_type,description,is_featured)
SELECT id,'Colmar Tropicale Convention Center','colmar-tropicale-convention-center','Convention Centre','Multi-purpose convention and meeting facility serving conferences, functions and events.',1 FROM location_categories WHERE slug='facilities';
INSERT IGNORE INTO locations(category_id,name,slug,location_type,description,is_featured)
SELECT id,'Masjid Berjaya Hills Resort','masjid-berjaya-hills-resort','Religious Facility','Prayer facility serving guests and visitors at Berjaya Hills Resort.',0 FROM location_categories WHERE slug='facilities';
INSERT IGNORE INTO locations(category_id,name,slug,location_type,description,is_featured)
SELECT id,'Botanical Garden','botanical-garden','Garden','Botanical garden within the Japanese Village area, surrounded by highland greenery.',1 FROM location_categories WHERE slug='activities';
INSERT IGNORE INTO locations(category_id,name,slug,location_type,description,is_featured)
SELECT id,'Berjaya Hills Horse Trails','berjaya-hills-horse-trails','Activity','Equestrian trail experience through the scenic forested surroundings of Berjaya Hills.',0 FROM location_categories WHERE slug='activities';
INSERT IGNORE INTO locations(category_id,name,slug,location_type,description,is_featured)
SELECT id,'Coniferous Forest','coniferous-forest','Nature','Highland forest attraction featuring cool-climate greenery and scenic nature surroundings.',0 FROM location_categories WHERE slug='activities';
INSERT IGNORE INTO locations(category_id,name,slug,location_type,description,is_featured)
SELECT id,'9D Motion Adventure','9d-motion-adventure','Activity','Immersive motion-based entertainment experience for visitors and families.',0 FROM location_categories WHERE slug='activities';
