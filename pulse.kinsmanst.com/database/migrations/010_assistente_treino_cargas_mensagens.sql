-- V10: torna a evolução de treino compatível com bancos já atualizados.
SET NAMES utf8mb4;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='student_anamneses' AND COLUMN_NAME='training_days_per_week')=0,'ALTER TABLE student_anamneses ADD COLUMN training_days_per_week TINYINT UNSIGNED NULL AFTER daily_routine','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='student_anamneses' AND COLUMN_NAME='training_minutes_per_session')=0,'ALTER TABLE student_anamneses ADD COLUMN training_minutes_per_session SMALLINT UNSIGNED NULL AFTER training_days_per_week','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='workout_logs' AND COLUMN_NAME='performed_load')=0,'ALTER TABLE workout_logs ADD COLUMN performed_load VARCHAR(40) NULL AFTER completed_at','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='workout_logs' AND COLUMN_NAME='performed_repetitions')=0,'ALTER TABLE workout_logs ADD COLUMN performed_repetitions VARCHAR(40) NULL AFTER performed_load','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='student_messages' AND COLUMN_NAME='reply_message')=0,'ALTER TABLE student_messages ADD COLUMN reply_message TEXT NULL AFTER message','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='student_messages' AND COLUMN_NAME='replied_at')=0,'ALTER TABLE student_messages ADD COLUMN replied_at DATETIME NULL AFTER reply_message','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
