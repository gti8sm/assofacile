CREATE TABLE treasury_closures (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  reason VARCHAR(255) NULL,
  closed_at DATETIME NOT NULL,
  closed_by_user_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_treasury_closures_tenant_id FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_treasury_closures_closed_by_user_id FOREIGN KEY (closed_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  UNIQUE KEY uniq_treasury_closures_tenant_period (tenant_id, start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
