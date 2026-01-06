ALTER TABLE project_documents
  ADD COLUMN deleted_at TIMESTAMP NULL AFTER created_at,
  ADD COLUMN deleted_by_user_id INT UNSIGNED NULL AFTER deleted_at,
  ADD COLUMN delete_reason VARCHAR(500) NULL AFTER deleted_by_user_id,
  ADD INDEX idx_pd_tenant_deleted (tenant_id, deleted_at),
  ADD CONSTRAINT fk_pd_deleted_by FOREIGN KEY (deleted_by_user_id) REFERENCES users(id) ON DELETE SET NULL;
