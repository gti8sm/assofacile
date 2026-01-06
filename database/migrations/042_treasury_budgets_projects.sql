ALTER TABLE treasury_budgets
  ADD COLUMN project_id BIGINT UNSIGNED NULL AFTER is_active,
  ADD INDEX idx_tb_tenant_project (tenant_id, project_id),
  ADD CONSTRAINT fk_tb_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL;
