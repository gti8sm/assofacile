ALTER TABLE membership_subscriptions
  ADD COLUMN paid_at DATETIME NULL,
  ADD COLUMN payment_provider VARCHAR(32) NULL,
  ADD COLUMN payment_external_id VARCHAR(128) NULL,
  ADD COLUMN payment_meta_json TEXT NULL;

CREATE TABLE helloasso_webhook_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  event_key VARCHAR(190) NOT NULL,
  event_type VARCHAR(32) NOT NULL,
  payload_json MEDIUMTEXT NOT NULL,
  received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME NULL,
  UNIQUE KEY uniq_ha_event (tenant_id, event_key),
  INDEX idx_ha_tenant (tenant_id),
  CONSTRAINT fk_ha_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
