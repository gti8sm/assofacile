CREATE TABLE tiers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  member_id INT UNSIGNED NULL,
  name VARCHAR(190) NOT NULL,
  normalized_name VARCHAR(190) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_tiers_tenant_member (tenant_id, member_id),
  UNIQUE KEY uniq_tiers_tenant_norm (tenant_id, normalized_name),
  INDEX idx_tiers_tenant_name (tenant_id, name),
  CONSTRAINT fk_tiers_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_tiers_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tier_tags (
  tier_id BIGINT UNSIGNED NOT NULL,
  tag VARCHAR(32) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (tier_id, tag),
  INDEX idx_tier_tags_tag (tag),
  CONSTRAINT fk_tier_tags_tier FOREIGN KEY (tier_id) REFERENCES tiers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE treasury_transactions
  ADD COLUMN tier_id BIGINT UNSIGNED NULL AFTER counterparty,
  ADD INDEX idx_tt_tier (tenant_id, tier_id),
  ADD CONSTRAINT fk_tt_tier FOREIGN KEY (tier_id) REFERENCES tiers(id) ON DELETE SET NULL;
