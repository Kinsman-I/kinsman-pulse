CREATE TABLE IF NOT EXISTS professional_addon_orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  professional_id BIGINT UNSIGNED NOT NULL,
  addon_code VARCHAR(60) NOT NULL,
  price_cents INT UNSIGNED NOT NULL,
  status ENUM('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
  asaas_checkout_id VARCHAR(100) NULL UNIQUE,
  external_reference VARCHAR(200) NOT NULL UNIQUE,
  paid_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_addon_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_addon_professional FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE CASCADE,
  INDEX idx_addon_professional_status (professional_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
