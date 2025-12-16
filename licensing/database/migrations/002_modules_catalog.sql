CREATE TABLE modules_catalog (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  module_key VARCHAR(64) NOT NULL,
  name VARCHAR(190) NOT NULL,
  price_month_cents INT UNSIGNED NOT NULL DEFAULT 0,
  price_year_cents INT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_modules_catalog_key (module_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE license_module_subscriptions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  license_id INT UNSIGNED NOT NULL,
  module_id INT UNSIGNED NOT NULL,
  billing_period VARCHAR(16) NOT NULL,
  valid_until DATE NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_lms_license FOREIGN KEY (license_id) REFERENCES licenses(id) ON DELETE CASCADE,
  CONSTRAINT fk_lms_module FOREIGN KEY (module_id) REFERENCES modules_catalog(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_lms_license_module (license_id, module_id),
  INDEX idx_lms_license (license_id),
  INDEX idx_lms_valid (valid_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO modules_catalog (module_key, name, price_month_cents, price_year_cents, is_active)
VALUES
  ('treasury', 'Trésorerie', 900, 9000, 1),
  ('drive', 'Google Drive', 500, 5000, 1);

INSERT IGNORE INTO license_module_subscriptions (license_id, module_id, billing_period, valid_until, is_active)
SELECT l.id, mc.id, 'annual', IFNULL(l.valid_until, DATE_ADD(CURDATE(), INTERVAL 10 YEAR)), 1
FROM licenses l
INNER JOIN modules_catalog mc ON mc.module_key = 'treasury'
WHERE l.is_revoked = 0;
