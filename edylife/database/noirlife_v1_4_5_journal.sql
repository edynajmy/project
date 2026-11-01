-- NoirLife v1.4.5 Calendar & Daily Journal
-- Select the existing `noirlife` database in phpMyAdmin before importing.
CREATE TABLE IF NOT EXISTS daily_entries (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  entry_date DATE NOT NULL,
  title VARCHAR(160) NOT NULL,
  category VARCHAR(40) NOT NULL DEFAULT 'Personal',
  mood VARCHAR(24) DEFAULT NULL,
  body TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_daily_entries_date (entry_date),
  KEY idx_daily_entries_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
