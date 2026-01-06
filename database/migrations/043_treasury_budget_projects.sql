CREATE TABLE treasury_budget_projects (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  budget_id BIGINT UNSIGNED NOT NULL,
  project_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_tbp_tenant_budget_project (tenant_id, budget_id, project_id),
  INDEX idx_tbp_tenant_budget (tenant_id, budget_id),
  INDEX idx_tbp_tenant_project (tenant_id, project_id),
  CONSTRAINT fk_tbp_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_tbp_budget FOREIGN KEY (budget_id) REFERENCES treasury_budgets(id) ON DELETE CASCADE,
  CONSTRAINT fk_tbp_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
