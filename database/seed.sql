-- Demo host and upcoming sample events for local development.
-- Safe to re-run: the demo user is reused and existing sample titles are skipped.
-- Run after importing event_schema_v2.sql.
USE event_platform;

-- Demo login: demo.host@showup.test / ShowUpDemo2026
INSERT INTO users (full_name, email, password_hash, city)
VALUES (
  'ShowUp Demo Host',
  'demo.host@showup.test',
  '$2y$12$9j4cfQLeI9oUGH.HbP2L7.i1GDJ9fJcgRHJM2zMkYFqNwE.0Ofevu',
  'Karachi'
)
ON DUPLICATE KEY UPDATE user_id = LAST_INSERT_ID(user_id);

SET @demo_host_id = LAST_INSERT_ID();

INSERT INTO events (
  host_id, category_id, title, description, event_mode,
  venue_name, address, online_link, location, city,
  start_datetime, end_datetime, capacity, checkin_code, status
)
SELECT
  @demo_host_id,
  c.category_id,
  sample.title,
  sample.description,
  sample.event_mode,
  sample.venue_name,
  sample.address,
  sample.online_link,
  sample.location,
  sample.city,
  sample.start_datetime,
  sample.end_datetime,
  sample.capacity,
  sample.checkin_code,
  'published'
FROM (
  SELECT 'Technology' AS category_name, 'Introduction to Web Development' AS title,
         'A beginner-friendly session covering HTML, CSS and the basics of building for the web.' AS description,
         'physical' AS event_mode, 'Community Learning Hall' AS venue_name,
         '12 Garden Road' AS address, NULL AS online_link, 'Community Learning Hall, Karachi' AS location,
         'Karachi' AS city, DATE_ADD(NOW(), INTERVAL 3 DAY) AS start_datetime,
         DATE_ADD(NOW(), INTERVAL 3 DAY) + INTERVAL 2 HOUR AS end_datetime,
         30 AS capacity, 'WEB247' AS checkin_code
  UNION ALL SELECT 'Technology', 'Getting Started with Open Source',
         'Learn how to find a project, make a small contribution and collaborate with other developers.',
         'online', NULL, NULL, 'https://example.com/showup-demo-open-source',
         'Online', NULL, DATE_ADD(NOW(), INTERVAL 8 DAY),
         DATE_ADD(NOW(), INTERVAL 8 DAY) + INTERVAL 1 HOUR, 100, 'OPN248'
  UNION ALL SELECT 'Sports', 'Weekend Community Football',
         'A friendly, all-levels football game. Bring water and arrive a little early to warm up.',
         'physical', 'Riverside Sports Ground', '5 Park Avenue', NULL,
         'Riverside Sports Ground, Karachi', 'Karachi',
         DATE_ADD(NOW(), INTERVAL 5 DAY), DATE_ADD(NOW(), INTERVAL 5 DAY) + INTERVAL 2 HOUR,
         22, 'FTB249'
  UNION ALL SELECT 'Arts', 'Watercolor Sketching Social',
         'Practice simple watercolor techniques in a relaxed group. Basic supplies are provided.',
         'physical', 'Studio Corner', '27 Art Street', NULL, 'Studio Corner, Karachi', 'Karachi',
         DATE_ADD(NOW(), INTERVAL 7 DAY), DATE_ADD(NOW(), INTERVAL 7 DAY) + INTERVAL 2 HOUR,
         16, 'ART250'
  UNION ALL SELECT 'Education', 'Study Skills and Time Management',
         'Share practical ways to plan a study week, stay focused and prepare for exams.',
         'online', NULL, NULL, 'https://example.com/showup-demo-study-skills',
         'Online', NULL, DATE_ADD(NOW(), INTERVAL 10 DAY),
         DATE_ADD(NOW(), INTERVAL 10 DAY) + INTERVAL 1 HOUR, 60, 'EDU251'
  UNION ALL SELECT 'Food', 'Community Cooking: Local Favorites',
         'Cook and share a few favorite local dishes. Please mention dietary needs when you RSVP.',
         'physical', 'Neighborhood Kitchen', '8 Market Lane', NULL,
         'Neighborhood Kitchen, Karachi', 'Karachi',
         DATE_ADD(NOW(), INTERVAL 12 DAY), DATE_ADD(NOW(), INTERVAL 12 DAY) + INTERVAL 3 HOUR,
         18, 'FOO252'
  UNION ALL SELECT 'Networking', 'Creative Professionals Coffee Meetup',
         'Meet local designers, writers and makers for informal conversation and idea sharing.',
         'physical', 'Bean & Leaf Cafe', '19 Main Boulevard', NULL,
         'Bean & Leaf Cafe, Karachi', 'Karachi',
         DATE_ADD(NOW(), INTERVAL 14 DAY), DATE_ADD(NOW(), INTERVAL 14 DAY) + INTERVAL 2 HOUR,
         24, 'NET253'
  UNION ALL SELECT 'Gaming', 'Casual Online Co-op Game Night',
         'An easygoing online game night. The host will share the game and meeting details with attendees.',
         'online', NULL, NULL, 'https://example.com/showup-demo-game-night',
         'Online', NULL, DATE_ADD(NOW(), INTERVAL 16 DAY),
         DATE_ADD(NOW(), INTERVAL 16 DAY) + INTERVAL 2 HOUR, 12, 'GAM254'
  UNION ALL SELECT 'Sports', 'Sunrise Walk and Stretch',
         'Start the day with a gentle group walk followed by a short stretching session.',
         'physical', 'Seaview Park Entrance', '2 Coast Road', NULL,
         'Seaview Park Entrance, Karachi', 'Karachi',
         DATE_ADD(NOW(), INTERVAL 19 DAY), DATE_ADD(NOW(), INTERVAL 19 DAY) + INTERVAL 1 HOUR,
         25, 'WLK255'
  UNION ALL SELECT 'Technology', 'Build a Tiny JavaScript Project',
         'Follow along as we build a small browser project and discuss how to keep learning JavaScript.',
         'online', NULL, NULL, 'https://example.com/showup-demo-javascript',
         'Online', NULL, DATE_ADD(NOW(), INTERVAL 21 DAY),
         DATE_ADD(NOW(), INTERVAL 21 DAY) + INTERVAL 2 HOUR, 80, 'JSX256'
) AS sample
JOIN categories c ON c.name = sample.category_name
WHERE NOT EXISTS (
  SELECT 1
  FROM events existing
  WHERE existing.host_id = @demo_host_id
    AND existing.title = sample.title
);
