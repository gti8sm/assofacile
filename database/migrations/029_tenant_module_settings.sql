CREATE TABLE tenant_module_settings (
  tenant_id INT UNSIGNED NOT NULL,
  module_key VARCHAR(64) NOT NULL,
  setting_key VARCHAR(64) NOT NULL,
  value_json TEXT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (tenant_id, module_key, setting_key),
  INDEX idx_tms_tenant_module (tenant_id, module_key),
  CONSTRAINT fk_tms_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
