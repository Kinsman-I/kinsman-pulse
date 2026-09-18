-- V6: adiciona cronômetro de sessão e informações de anamnese.
SET NAMES utf8mb4;

ALTER TABLE workout_sessions
  ADD COLUMN timer_started_at DATETIME NULL AFTER started_at;

CREATE TABLE IF NOT EXISTS student_anamneses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id BIGINT UNSIGNED NOT NULL,
  daily_routine TEXT NULL,
  meals_at_work TINYINT(1) NULL,
  diseases TEXT NULL,
  controlled_medications TEXT NULL,
  injuries_surgeries TEXT NULL,
  has_allergies TINYINT(1) NOT NULL DEFAULT 0,
  allergies TEXT NULL,
  used_ergogenics TINYINT(1) NOT NULL DEFAULT 0,
  ergogenics TEXT NULL,
  objective TEXT NULL,
  submitted_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_anamnesis_student (student_id),
  CONSTRAINT fk_anamnesis_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
