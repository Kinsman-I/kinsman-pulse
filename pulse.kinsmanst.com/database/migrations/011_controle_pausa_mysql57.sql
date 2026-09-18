-- V11: aplica campos do cronômetro de modo compatível com MySQL 5.7.
SET NAMES utf8mb4;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='workout_sessions' AND COLUMN_NAME='timer_started_at')=0,'ALTER TABLE workout_sessions ADD COLUMN timer_started_at DATETIME NULL AFTER started_at','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='workout_sessions' AND COLUMN_NAME='paused_at')=0,'ALTER TABLE workout_sessions ADD COLUMN paused_at DATETIME NULL AFTER timer_started_at','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='workout_sessions' AND COLUMN_NAME='paused_seconds')=0,'ALTER TABLE workout_sessions ADD COLUMN paused_seconds INT UNSIGNED NOT NULL DEFAULT 0 AFTER paused_at','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
