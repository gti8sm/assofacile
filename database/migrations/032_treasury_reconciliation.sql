ALTER TABLE treasury_transactions
  ADD COLUMN payment_method VARCHAR(32) NULL AFTER type,
  ADD COLUMN counterparty VARCHAR(190) NULL AFTER label,
  ADD COLUMN reference VARCHAR(190) NULL AFTER counterparty,
  ADD COLUMN cleared_by_user_id INT UNSIGNED NULL AFTER cleared_at,
  ADD CONSTRAINT fk_tt_cleared_by_user FOREIGN KEY (cleared_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  ADD INDEX idx_tt_payment_method (tenant_id, payment_method),
  ADD INDEX idx_tt_counterparty (tenant_id, counterparty);
