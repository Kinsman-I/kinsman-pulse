ALTER TABLE professionals
  ADD COLUMN trial_ends_at DATETIME NULL AFTER subscription_status;

UPDATE professionals
SET trial_ends_at = DATE_ADD(created_at, INTERVAL 7 DAY)
WHERE subscription_status = 'trial' AND trial_ends_at IS NULL;
