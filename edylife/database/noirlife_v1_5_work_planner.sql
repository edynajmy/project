-- NoirLife v1.5 Work Planner migration. Select existing noirlife database first.
CREATE TABLE IF NOT EXISTS work_items (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT,
 title VARCHAR(160) NOT NULL,
 description TEXT NULL,
 category VARCHAR(60) NOT NULL DEFAULT 'General',
 priority ENUM('Low','Medium','High') NOT NULL DEFAULT 'Medium',
 status ENUM('To do','In progress','Done') NOT NULL DEFAULT 'To do',
 start_date DATE NULL,
 due_date DATE NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (id), KEY idx_work_status(status), KEY idx_work_due(due_date), KEY idx_work_category(category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
