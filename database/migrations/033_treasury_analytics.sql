CREATE TABLE treasury_analytics_axes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  `key` VARCHAR(64) NOT NULL,
  label VARCHAR(190) NOT NULL,
  position INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_taa_tenant_key (tenant_id, `key`),
  INDEX idx_taa_tenant_active (tenant_id, is_active, position),
  CONSTRAINT fk_taa_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE treasury_analytics_values (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  axis_id INT UNSIGNED NOT NULL,
  label VARCHAR(190) NOT NULL,
  position INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_tav_axis_label (tenant_id, axis_id, label),
  INDEX idx_tav_tenant_axis_active (tenant_id, axis_id, is_active, position),
  CONSTRAINT fk_tav_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_tav_axis FOREIGN KEY (axis_id) REFERENCES treasury_analytics_axes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE treasury_transaction_allocations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  transaction_id BIGINT UNSIGNED NOT NULL,
  axis_id INT UNSIGNED NOT NULL,
  value_id BIGINT UNSIGNED NOT NULL,
  amount_cents INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_tta_tx_axis_value (tenant_id, transaction_id, axis_id, value_id),
  INDEX idx_tta_tenant_tx (tenant_id, transaction_id),
  INDEX idx_tta_tenant_axis_value (tenant_id, axis_id, value_id),
  CONSTRAINT fk_tta_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_tta_tx FOREIGN KEY (transaction_id) REFERENCES treasury_transactions(id) ON DELETE CASCADE,
  CONSTRAINT fk_tta_axis FOREIGN KEY (axis_id) REFERENCES treasury_analytics_axes(id) ON DELETE CASCADE,
  CONSTRAINT fk_tta_value FOREIGN KEY (value_id) REFERENCES treasury_analytics_values(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
