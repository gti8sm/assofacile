ALTER TABLE treasury_transactions
  ADD COLUMN deleted_at TIMESTAMP NULL AFTER created_at,
  ADD COLUMN deleted_by_user_id INT UNSIGNED NULL AFTER deleted_at,
  ADD COLUMN delete_reason VARCHAR(255) NULL AFTER deleted_by_user_id,
  ADD CONSTRAINT fk_tt_deleted_by_user FOREIGN KEY (deleted_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  ADD INDEX idx_tt_deleted_at (tenant_id, deleted_at);
