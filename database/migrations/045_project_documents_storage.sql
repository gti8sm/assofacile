ALTER TABLE project_documents
  ADD COLUMN storage_driver ENUM('local','gdrive') NOT NULL DEFAULT 'local' AFTER project_id,
  ADD COLUMN local_path VARCHAR(255) NULL AFTER storage_driver,
  ADD COLUMN gdrive_file_id VARCHAR(255) NULL AFTER local_path,
  ADD COLUMN original_name VARCHAR(255) NULL AFTER gdrive_file_id,
  ADD COLUMN mime_type VARCHAR(190) NULL AFTER original_name,
  ADD COLUMN size_bytes INT UNSIGNED NULL AFTER mime_type,
  ADD INDEX idx_pd_tenant_driver (tenant_id, storage_driver);
