CREATE TABLE treasury_attachments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  transaction_id BIGINT UNSIGNED NOT NULL,
  storage_driver ENUM('local','gdrive') NOT NULL DEFAULT 'local',
  local_path VARCHAR(255) NULL,
  gdrive_file_id VARCHAR(255) NULL,
  original_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(190) NOT NULL,
  size_bytes INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ta_tenant_tx (tenant_id, transaction_id),
  CONSTRAINT fk_ta_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_ta_tx FOREIGN KEY (transaction_id) REFERENCES treasury_transactions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
