-- V9: evolui assinatura, cancelamento e programa de indicações.
ALTER TABLE professionals
  ADD COLUMN access_until DATETIME NULL AFTER trial_ends_at,
  ADD COLUMN cancellation_requested_at DATETIME NULL AFTER access_until,
  ADD COLUMN referral_code VARCHAR(24) NULL AFTER cancellation_requested_at,
  ADD COLUMN referred_by_professional_id BIGINT UNSIGNED NULL AFTER referral_code,
  ADD UNIQUE KEY uq_professional_referral_code (referral_code),
  ADD INDEX idx_professional_referred_by (referred_by_professional_id);

ALTER TABLE professionals
  MODIFY subscription_status ENUM('trial','active','past_due','cancelled') NOT NULL DEFAULT 'cancelled';

ALTER TABLE professional_subscriptions
  MODIFY status ENUM('trial','active','past_due','cancelled') NOT NULL DEFAULT 'cancelled';

UPDATE professionals
SET referral_code = CONCAT('KIN', UPPER(LPAD(HEX(id), 8, '0')))
WHERE referral_code IS NULL;

CREATE TABLE IF NOT EXISTS referral_rewards (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  referrer_professional_id BIGINT UNSIGNED NOT NULL,
  referred_professional_id BIGINT UNSIGNED NOT NULL,
  status ENUM('pending','available','applied','cancelled') NOT NULL DEFAULT 'pending',
  qualified_at DATETIME NULL,
  benefit_due_date DATE NULL,
  applied_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_referral_referrer FOREIGN KEY (referrer_professional_id) REFERENCES professionals(id) ON DELETE CASCADE,
  CONSTRAINT fk_referral_referred FOREIGN KEY (referred_professional_id) REFERENCES professionals(id) ON DELETE CASCADE,
  UNIQUE KEY uq_referral_referred (referred_professional_id),
  INDEX idx_referral_referrer_status (referrer_professional_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO referral_rewards(referrer_professional_id,referred_professional_id,status)
SELECT referred_by_professional_id,id,'pending'
FROM professionals
WHERE referred_by_professional_id IS NOT NULL;

UPDATE professionals
SET subscription_status='cancelled',trial_ends_at=NULL,access_until=NULL
WHERE subscription_status='trial';

UPDATE professional_subscriptions
SET status='cancelled',ends_at=NOW()
WHERE status='trial';
