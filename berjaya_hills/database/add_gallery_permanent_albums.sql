-- v19: Permanent public gallery starter albums.
-- Safe to run after the main schema. Existing albums with the same slug are skipped.
INSERT INTO gallery_albums(title,slug,description,cover_image,is_public)
SELECT 'Colmar Tropicale','colmar-tropicale','French-inspired architecture, village streets and the character of Berjaya Hills.','https://berjayahills.org/img/colmar-tropicale-valley.jpg',1
WHERE NOT EXISTS (SELECT 1 FROM gallery_albums WHERE slug='colmar-tropicale');
INSERT INTO gallery_albums(title,slug,description,cover_image,is_public)
SELECT 'Japanese Village','japanese-village','A tranquil highland garden experience with Japanese-inspired landscapes, water features and greenery.','https://www.berjayahills.org/img/japanese-village-garden.jpg',1
WHERE NOT EXISTS (SELECT 1 FROM gallery_albums WHERE slug='japanese-village');
INSERT INTO gallery_albums(title,slug,description,cover_image,is_public)
SELECT 'Nature & Adventure','nature-adventure','Highland scenery, golf, botanical trails and outdoor experiences across Berjaya Hills.','https://www.berjayahills.org/img/golf-country-club-activities.jpg',1
WHERE NOT EXISTS (SELECT 1 FROM gallery_albums WHERE slug='nature-adventure');
INSERT INTO gallery_albums(title,slug,description,cover_image,is_public)
SELECT 'Event Venues','event-venues','Spaces prepared for meetings, celebrations, weddings, conferences and private occasions.','https://www.berjayahills.com/assets/img/La-Serre-Glasshouse-600x400.jpg',1
WHERE NOT EXISTS (SELECT 1 FROM gallery_albums WHERE slug='event-venues');

INSERT INTO gallery_images(album_id,image_path,caption,sort_order)
SELECT a.id,'https://berjayahills.org/img/colmar-tropicale-valley.jpg','Colmar Tropicale entrance walkway',1 FROM gallery_albums a WHERE a.slug='colmar-tropicale' AND NOT EXISTS (SELECT 1 FROM gallery_images gi WHERE gi.album_id=a.id);
INSERT INTO gallery_images(album_id,image_path,caption,sort_order)
SELECT a.id,'https://ak-d.tripcdn.com/images/1mi4c224x98dv6ctyFEB4_Q90.jpg?proc=source%2Ftrip','Colmar Tropicale entrance gate',2 FROM gallery_albums a WHERE a.slug='colmar-tropicale' AND NOT EXISTS (SELECT 1 FROM gallery_images gi WHERE gi.album_id=a.id AND gi.sort_order=2);
INSERT INTO gallery_images(album_id,image_path,caption,sort_order)
SELECT a.id,'https://q-xx.bstatic.com/xdata/images/hotel/max1024x768/10368584.jpg?k=1ee0943be02941d379830369d54f00f551684f079c86310f479a583aafaa7aef&o=','Aerial view of Colmar Tropicale',3 FROM gallery_albums a WHERE a.slug='colmar-tropicale' AND NOT EXISTS (SELECT 1 FROM gallery_images gi WHERE gi.album_id=a.id AND gi.sort_order=3);

INSERT INTO gallery_images(album_id,image_path,caption,sort_order)
SELECT a.id,'https://www.berjayahills.org/img/japanese-village-garden.jpg','Japanese Village garden',1 FROM gallery_albums a WHERE a.slug='japanese-village' AND NOT EXISTS (SELECT 1 FROM gallery_images gi WHERE gi.album_id=a.id);
INSERT INTO gallery_images(album_id,image_path,caption,sort_order)
SELECT a.id,'https://berjayahills.org/img/botanical-garden.jpg','Botanical Garden trail',2 FROM gallery_albums a WHERE a.slug='japanese-village' AND NOT EXISTS (SELECT 1 FROM gallery_images gi WHERE gi.album_id=a.id AND gi.sort_order=2);

INSERT INTO gallery_images(album_id,image_path,caption,sort_order)
SELECT a.id,'https://www.berjayahills.org/img/golf-country-club-activities.jpg','Berjaya Hills Golf & Country Club',1 FROM gallery_albums a WHERE a.slug='nature-adventure' AND NOT EXISTS (SELECT 1 FROM gallery_images gi WHERE gi.album_id=a.id);
INSERT INTO gallery_images(album_id,image_path,caption,sort_order)
SELECT a.id,'https://www.berjayahills.org/img/activities-horse-trails.jpg','Horse Trails',2 FROM gallery_albums a WHERE a.slug='nature-adventure' AND NOT EXISTS (SELECT 1 FROM gallery_images gi WHERE gi.album_id=a.id AND gi.sort_order=2);
INSERT INTO gallery_images(album_id,image_path,caption,sort_order)
SELECT a.id,'https://berjayahills.org/img/botanical-garden.jpg','Botanical Garden',3 FROM gallery_albums a WHERE a.slug='nature-adventure' AND NOT EXISTS (SELECT 1 FROM gallery_images gi WHERE gi.album_id=a.id AND gi.sort_order=3);

INSERT INTO gallery_images(album_id,image_path,caption,sort_order)
SELECT a.id,'https://www.berjayahills.com/assets/img/La-Serre-Glasshouse-600x400.jpg','La Serre Glasshouse / Glass Hall',1 FROM gallery_albums a WHERE a.slug='event-venues' AND NOT EXISTS (SELECT 1 FROM gallery_images gi WHERE gi.album_id=a.id);
INSERT INTO gallery_images(album_id,image_path,caption,sort_order)
SELECT a.id,'https://www.berjayahills.com/assets/img/Le-Chevalier-600x400.jpg','Le Chevalier meeting setup',2 FROM gallery_albums a WHERE a.slug='event-venues' AND NOT EXISTS (SELECT 1 FROM gallery_images gi WHERE gi.album_id=a.id AND gi.sort_order=2);
INSERT INTO gallery_images(album_id,image_path,caption,sort_order)
SELECT a.id,'https://b2b.travelerts.com.tw/eWeb_ts/IMGDB/000112/000335/00005701.JPG','The Chateau',3 FROM gallery_albums a WHERE a.slug='event-venues' AND NOT EXISTS (SELECT 1 FROM gallery_images gi WHERE gi.album_id=a.id AND gi.sort_order=3);
