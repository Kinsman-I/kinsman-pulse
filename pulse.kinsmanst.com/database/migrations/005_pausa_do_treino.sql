SET NAMES utf8mb4;

ALTER TABLE workout_sessions
  ADD COLUMN paused_at DATETIME NULL AFTER started_at,
  ADD COLUMN paused_seconds INT UNSIGNED NOT NULL DEFAULT 0 AFTER paused_at;
