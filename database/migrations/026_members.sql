CREATE TABLE members (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  first_name VARCHAR(190) NULL,
  last_name VARCHAR(190) NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(64) NULL,
  address TEXT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'active',
  member_since DATE NULL,
  membership_paid_until DATE NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_members_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  INDEX idx_members_tenant (tenant_id),
  INDEX idx_members_status (tenant_id, status),
  UNIQUE KEY uniq_members_tenant_email (tenant_id, email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Core Adhérents : module toujours présent/activé (mais non payant).
INSERT IGNORE INTO modules (module_key, name) VALUES ('members', 'Adhérents');

INSERT IGNORE INTO tenant_modules (tenant_id, module_id, is_enabled, enabled_at)
SELECT t.id, m.id, 1, CURRENT_TIMESTAMP
FROM tenants t
INNER JOIN modules m ON m.module_key = 'members';
