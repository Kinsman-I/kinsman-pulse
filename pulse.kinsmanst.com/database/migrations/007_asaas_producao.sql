ALTER TABLE professionals
  ADD COLUMN asaas_customer_id VARCHAR(80) NULL AFTER subscription_status;

ALTER TABLE professional_subscriptions
  ADD COLUMN asaas_checkout_id VARCHAR(100) NULL AFTER status,
  ADD COLUMN asaas_subscription_id VARCHAR(100) NULL AFTER asaas_checkout_id,
  ADD COLUMN asaas_customer_id VARCHAR(80) NULL AFTER asaas_subscription_id,
  ADD COLUMN external_reference VARCHAR(200) NULL AFTER asaas_customer_id,
  ADD UNIQUE KEY uq_subscription_asaas_checkout (asaas_checkout_id),
  ADD INDEX idx_subscription_asaas_subscription (asaas_subscription_id);

CREATE TABLE asaas_webhook_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id VARCHAR(180) NOT NULL UNIQUE,
  event_type VARCHAR(100) NOT NULL,
  payload JSON NOT NULL,
  processed_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
