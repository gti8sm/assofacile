-- Crée une association et un admin par défaut.
-- Mot de passe: admin123 (à changer)

INSERT INTO tenants (name) VALUES ('Association Démo');

-- Génère le hash via PHP: password_hash('admin123', PASSWORD_DEFAULT)
-- Hash pré-calculé (PHP 8.x):
INSERT INTO users (tenant_id, email, password_hash, full_name, is_active)
VALUES (1, 'admin@demo.local', '$2y$10$2cX0jG8hA8Yxj.1xB7j9eO6s9k2Wm3Dq8G2bKQ3v6t9h0B2xG9z7u', 'Admin Démo', 1);
