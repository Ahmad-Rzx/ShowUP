-- Adds the physical/online split to an existing event_platform database.
-- Run once only:
--   mysql -u showup -p event_platform < database/migrations/2026_11_05_event_modes.sql
--
-- Fresh installs should import database/event_schema_v2.sql instead; it already
-- contains these columns, the constraint and the updated reliability view.

USE event_platform;

ALTER TABLE events
  ADD COLUMN event_mode  ENUM('physical','online') NOT NULL DEFAULT 'physical' AFTER description,
  ADD COLUMN venue_name  VARCHAR(150) NULL AFTER event_mode,
  ADD COLUMN address     VARCHAR(255) NULL AFTER venue_name,
  ADD COLUMN online_link VARCHAR(500) NULL AFTER address,
  ADD INDEX idx_events_mode (event_mode);

-- Rows created before this migration only had the free-text location column.
-- Treat them as physical events and copy the old location into the new fields.
UPDATE events
SET venue_name = location,
    address    = location
WHERE event_mode = 'physical'
  AND venue_name IS NULL;

ALTER TABLE events
  ADD CONSTRAINT chk_event_mode_fields CHECK (
    (event_mode = 'physical' AND venue_name IS NOT NULL AND address IS NOT NULL AND online_link IS NULL)
    OR
    (event_mode = 'online'   AND online_link IS NOT NULL AND venue_name IS NULL AND address IS NULL)
  );

CREATE OR REPLACE VIEW user_reliability AS
SELECT
  r.user_id,
  SUM(r.status = 'registered' AND r.checked_in_at IS NOT NULL) AS attended,
  SUM(r.status = 'registered' AND r.confirmed_at IS NOT NULL)  AS confirmed,
  SUM(r.status = 'registered' AND r.checked_in_at IS NULL
      AND e.end_datetime < NOW()) AS no_shows,
  SUM(r.status = 'cancelled'
      AND r.cancelled_at >= e.start_datetime - INTERVAL 24 HOUR) AS late_cancels
FROM registrations r
JOIN events e ON e.event_id = r.event_id
WHERE e.status = 'published'
GROUP BY r.user_id;
