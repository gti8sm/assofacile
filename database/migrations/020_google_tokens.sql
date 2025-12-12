CREATE TABLE tenant_google_tokens (
  tenant_id INT UNSIGNED NOT NULL PRIMARY KEY,
  drive_access_token TEXT NULL,
  drive_refresh_token TEXT NULL,
  drive_token_expires_at INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_tgt_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
