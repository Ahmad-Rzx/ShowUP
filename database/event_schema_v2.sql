-- Event platform with commitment-based RSVPs -- MySQL 8.0+ (v2, simplified; replaces schema.sql)
-- Import: mysql -u root -p < event_schema_v2.sql   (or phpMyAdmin > SQL tab)
-- Existing databases should use database/migrations/2026_11_05_event_modes.sql instead.

DROP DATABASE IF EXISTS event_platform;
CREATE DATABASE event_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE event_platform;

-- 1. USERS -------------------------------------------------------------
CREATE TABLE users (
  user_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name      VARCHAR(100) NOT NULL,
  email          VARCHAR(120) NOT NULL UNIQUE,        -- login identifier
  password_hash  VARCHAR(255) NOT NULL,               -- password_hash() output, never plain text
  city           VARCHAR(80)  NULL,
  bio            VARCHAR(500) NULL,
  created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at  DATETIME     NULL
) ENGINE=InnoDB;

-- 2. CATEGORIES --------------------------------------------------------
CREATE TABLE categories (
  category_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(60) NOT NULL UNIQUE,
  slug         VARCHAR(60) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- 3. EVENTS ------------------------------------------------------------
CREATE TABLE events (
  event_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  host_id         INT UNSIGNED NOT NULL,              -- the user who hosts the event
  category_id     INT UNSIGNED NOT NULL,
  title           VARCHAR(150) NOT NULL,
  description     TEXT         NOT NULL,
  event_mode      ENUM('physical','online') NOT NULL DEFAULT 'physical',
  venue_name      VARCHAR(150) NULL,                  -- physical events only
  address         VARCHAR(255) NULL,                  -- physical events only
  online_link     VARCHAR(500) NULL,                  -- online events only
  location        VARCHAR(200) NOT NULL,              -- short card label: venue + city, or "Online"
  city            VARCHAR(80)  NULL,                  -- physical events only
  start_datetime  DATETIME     NOT NULL,
  end_datetime    DATETIME     NOT NULL,
  capacity        INT UNSIGNED NULL,                  -- NULL = unlimited seats
  checkin_code    CHAR(6)      NOT NULL,              -- random code the host shows at the venue
  status          ENUM('published','cancelled') NOT NULL DEFAULT 'published',
  created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_events_host     FOREIGN KEY (host_id)     REFERENCES users(user_id)          ON DELETE CASCADE,
  CONSTRAINT fk_events_category FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE RESTRICT,
  CONSTRAINT chk_event_dates    CHECK (end_datetime > start_datetime),
  CONSTRAINT chk_event_mode_fields CHECK (
    (event_mode = 'physical' AND venue_name IS NOT NULL AND address IS NOT NULL AND online_link IS NULL)
    OR
    (event_mode = 'online'   AND online_link IS NOT NULL AND venue_name IS NULL AND address IS NULL)
  ),
  INDEX idx_events_start    (start_datetime),
  INDEX idx_events_city     (city),
  INDEX idx_events_category (category_id),
  INDEX idx_events_mode     (event_mode),
  FULLTEXT idx_events_search (title, description)
) ENGINE=InnoDB;

-- 4. REGISTRATIONS  (users <-> events, many-to-many) -------------------
-- The "commitment" columns live here:
--   confirmed_at   : user pressed "I'm still coming" in the confirm window before the event
--   checked_in_at  : user entered the event's checkin_code at the venue (proof of attendance)
--   cancelled_at   : when they cancelled (a cancel within 24h of start counts as a late cancel)
CREATE TABLE registrations (
  registration_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id         INT UNSIGNED NOT NULL,
  user_id          INT UNSIGNED NOT NULL,
  status           ENUM('registered','waitlisted','cancelled') NOT NULL DEFAULT 'registered',
  registered_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  confirmed_at     DATETIME     NULL,
  checked_in_at    DATETIME     NULL,
  cancelled_at     DATETIME     NULL,
  CONSTRAINT fk_reg_event FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
  CONSTRAINT fk_reg_user  FOREIGN KEY (user_id)  REFERENCES users(user_id)   ON DELETE CASCADE,
  UNIQUE KEY uq_event_user (event_id, user_id),       -- one registration per user per event
  INDEX idx_reg_user (user_id)
) ENGINE=InnoDB;

-- 5. EVENT_REVIEWS  (only users with checked_in_at set may review: enforced in PHP) ----
CREATE TABLE event_reviews (
  review_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id    INT UNSIGNED NOT NULL,
  user_id     INT UNSIGNED NOT NULL,
  rating      TINYINT UNSIGNED NOT NULL,
  comment     VARCHAR(1000) NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_er_event FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
  CONSTRAINT fk_er_user  FOREIGN KEY (user_id)  REFERENCES users(user_id)   ON DELETE CASCADE,
  CONSTRAINT chk_rating  CHECK (rating BETWEEN 1 AND 5),
  UNIQUE KEY uq_review_event_user (event_id, user_id)
) ENGINE=InnoDB;

-- Reliability: derived, not stored (no extra table, nothing to keep in sync) ----------
-- The label uses attended / confirmed (see includes/event_helpers.php).
-- no_shows and late_cancels are kept for later reporting; the label does not use them.
CREATE VIEW user_reliability AS
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

-- Seed data ------------------------------------------------------------
INSERT INTO categories (name, slug) VALUES
  ('Technology',  'technology'),
  ('Sports',      'sports'),
  ('Arts',        'arts'),
  ('Education',   'education'),
  ('Food',        'food'),
  ('Networking',  'networking'),
  ('Gaming',      'gaming');
