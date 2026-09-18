-- V3: adiciona tempos de início e conclusão ao log de exercício.
SET NAMES utf8mb4;

ALTER TABLE workout_logs
  ADD COLUMN started_at DATETIME NULL AFTER workout_item_id,
  ADD COLUMN completed_at DATETIME NULL AFTER started_at;
