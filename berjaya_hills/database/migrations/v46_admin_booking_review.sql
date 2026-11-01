-- V46 admin booking review enhancements
-- Run once on an existing V45/V44 database.
ALTER TABLE bookings MODIFY status ENUM('draft','pending_review','pending_info','rejected','confirmed','cancelled','completed') DEFAULT 'draft';
SET @has_payment_method := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bookings' AND COLUMN_NAME='payment_method');
SET @sql := IF(@has_payment_method=0,'ALTER TABLE bookings ADD COLUMN payment_method VARCHAR(80) NULL AFTER total_estimate','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
