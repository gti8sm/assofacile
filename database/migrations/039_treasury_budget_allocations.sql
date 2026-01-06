CREATE TABLE treasury_budget_allocations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  transaction_id BIGINT UNSIGNED NOT NULL,
  budget_id BIGINT UNSIGNED NOT NULL,
  amount_cents INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_tba_tx_budget (tenant_id, transaction_id, budget_id),
  INDEX idx_tba_tenant_tx (tenant_id, transaction_id),
  INDEX idx_tba_tenant_budget (tenant_id, budget_id),
  CONSTRAINT fk_tba_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_tba_tx FOREIGN KEY (transaction_id) REFERENCES treasury_transactions(id) ON DELETE CASCADE,
  CONSTRAINT fk_tba_budget FOREIGN KEY (budget_id) REFERENCES treasury_budgets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
