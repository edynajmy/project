CREATE TABLE IF NOT EXISTS contact_inquiries (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  company VARCHAR(190) NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(50) NULL,
  event_type VARCHAR(100) NOT NULL,
  estimated_guests INT UNSIGNED NOT NULL DEFAULT 1,
  preferred_date DATE NULL,
  contact_preference ENUM('Email','Phone','WhatsApp') NOT NULL DEFAULT 'Email',
  message TEXT NOT NULL,
  status ENUM('new','in_progress','responded','closed') NOT NULL DEFAULT 'new',
  assigned_to BIGINT UNSIGNED NULL,
  admin_note TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX(status), INDEX(created_at),
  FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
