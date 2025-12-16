ALTER TABLE treasury_transactions
  ADD COLUMN is_cleared TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN cleared_at DATETIME NULL;

CREATE INDEX idx_tt_tenant_cleared ON treasury_transactions (tenant_id, is_cleared);
