ALTER TABLE tenants
  ADD COLUMN slug VARCHAR(190) NULL,
  ADD COLUMN public_domain VARCHAR(190) NULL,
  ADD COLUMN public_domain_status VARCHAR(16) NOT NULL DEFAULT 'pending',
  ADD COLUMN public_domain_verification_token VARCHAR(64) NULL,
  ADD COLUMN public_domain_verified_at TIMESTAMP NULL,
  ADD UNIQUE KEY uniq_tenants_slug (slug),
  ADD UNIQUE KEY uniq_tenants_public_domain (public_domain);

CREATE TABLE public_pages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  slug VARCHAR(190) NOT NULL,
  title VARCHAR(190) NOT NULL,
  is_home TINYINT(1) NOT NULL DEFAULT 0,
  is_published TINYINT(1) NOT NULL DEFAULT 0,
  published_revision_id INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_public_pages_tenant_slug (tenant_id, slug),
  KEY idx_public_pages_tenant_home (tenant_id, is_home),
  CONSTRAINT fk_public_pages_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE public_page_revisions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  page_id INT UNSIGNED NOT NULL,
  schema_version INT UNSIGNED NOT NULL DEFAULT 1,
  content_json LONGTEXT NOT NULL,
  created_by_user_id INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_public_revisions_page (page_id, id),
  CONSTRAINT fk_public_revisions_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_public_revisions_page FOREIGN KEY (page_id) REFERENCES public_pages(id) ON DELETE CASCADE,
  CONSTRAINT fk_public_revisions_user FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE public_pages
  ADD CONSTRAINT fk_public_pages_published_revision FOREIGN KEY (published_revision_id) REFERENCES public_page_revisions(id) ON DELETE SET NULL;

INSERT IGNORE INTO modules (module_key, name) VALUES ('public_site', 'Site public');
