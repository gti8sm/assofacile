-- Crée une association et un admin par défaut.
-- Mot de passe: admin123 (à changer)

INSERT INTO tenants (name) VALUES ('Association Démo');

-- Génère le hash via PHP: password_hash('admin123', PASSWORD_DEFAULT)
-- Hash pré-calculé (PHP 8.x):
INSERT INTO users (tenant_id, email, password_hash, full_name, is_active, is_admin)
VALUES (1, 'admin@demo.local', '$2y$10$2cX0jG8hA8Yxj.1xB7j9eO6s9k2Wm3Dq8G2bKQ3v6t9h0B2xG9z7u', 'Admin Démo', 1, 1);

INSERT IGNORE INTO modules (module_key, name) VALUES ('treasury', 'Trésorerie');
INSERT IGNORE INTO modules (module_key, name) VALUES ('drive', 'Google Drive');

INSERT IGNORE INTO tenant_modules (tenant_id, module_id, is_enabled, enabled_at)
SELECT 1, m.id, 1, CURRENT_TIMESTAMP
FROM modules m
WHERE m.module_key = 'treasury'
LIMIT 1;
