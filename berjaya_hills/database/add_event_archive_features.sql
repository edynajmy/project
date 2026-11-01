-- Optional storage for approved public guest testimonials on event recap pages.
CREATE TABLE IF NOT EXISTS event_testimonials (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id BIGINT UNSIGNED NOT NULL,
  guest_name VARCHAR(150) NOT NULL,
  quote TEXT NOT NULL,
  is_public TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE CASCADE,
  INDEX(event_id,is_public)
) ENGINE=InnoDB;
