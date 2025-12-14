CREATE TABLE tenant_licenses (
  tenant_id INT UNSIGNED NOT NULL PRIMARY KEY,
  license_key VARCHAR(190) NOT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'unknown',
  plan_type VARCHAR(16) NULL,
  valid_until DATE NULL,
  grace_until DATE NULL,
  last_checked_at DATETIME NULL,
  last_error VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_tenant_licenses_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_license_key (license_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
