CREATE TABLE projects (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL,
  description TEXT NULL,
  funding_type VARCHAR(64) NULL,
  starts_on DATE NULL,
  ends_on DATE NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'active',
  conclusion TEXT NULL,
  financial_summary TEXT NULL,
  created_by_user_id INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_projects_tenant (tenant_id),
  INDEX idx_projects_status (tenant_id, status),
  CONSTRAINT fk_projects_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE project_actions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  project_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL,
  description TEXT NULL,
  schedule_rule VARCHAR(64) NULL,
  starts_on DATE NULL,
  ends_on DATE NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_pa_project (tenant_id, project_id),
  CONSTRAINT fk_pa_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_pa_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE project_tasks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  project_id BIGINT UNSIGNED NOT NULL,
  action_id BIGINT UNSIGNED NULL,
  title VARCHAR(190) NOT NULL,
  description TEXT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'todo',
  due_on DATE NULL,
  assigned_tier_id BIGINT UNSIGNED NULL,
  assigned_user_id INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_pt_project (tenant_id, project_id),
  INDEX idx_pt_action (tenant_id, action_id),
  INDEX idx_pt_due (tenant_id, due_on),
  CONSTRAINT fk_pt_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_pt_action FOREIGN KEY (action_id) REFERENCES project_actions(id) ON DELETE SET NULL,
  CONSTRAINT fk_pt_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_pt_tier FOREIGN KEY (assigned_tier_id) REFERENCES tiers(id) ON DELETE SET NULL,
  CONSTRAINT fk_pt_user FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE project_participants (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  project_id BIGINT UNSIGNED NOT NULL,
  action_id BIGINT UNSIGNED NULL,
  tier_id BIGINT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  role VARCHAR(64) NOT NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pp_project (tenant_id, project_id),
  INDEX idx_pp_action (tenant_id, action_id),
  INDEX idx_pp_role (tenant_id, role),
  CONSTRAINT fk_pp_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_pp_action FOREIGN KEY (action_id) REFERENCES project_actions(id) ON DELETE SET NULL,
  CONSTRAINT fk_pp_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_pp_tier FOREIGN KEY (tier_id) REFERENCES tiers(id) ON DELETE SET NULL,
  CONSTRAINT fk_pp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
