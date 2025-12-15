<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Installer;
use App\Support\Session;
use App\Support\License;

final class InstallController
{
    public static function show(): void
    {
        if (Installer::isLocked()) {
            redirect('/login');
        }

        $error = Session::flash('error');

        $defaults = [
            'db_host' => '127.0.0.1',
            'db_port' => '3306',
            'db_name' => 'assofacile',
            'db_user' => 'root',
            'db_pass' => '',
            'tenant_name' => 'Mon association',
            'admin_email' => '',
            'admin_name' => '',
            'license_key' => '',
        ];

        $old = is_array($_SESSION['_old_install'] ?? null) ? (array)$_SESSION['_old_install'] : [];
        unset($_SESSION['_old_install']);

        $data = array_merge($defaults, array_intersect_key($old, $defaults));

        require base_path('views/install/index.php');
    }

    public static function submit(): void
    {
        if (Installer::isLocked()) {
            redirect('/login');
        }

        $dbHost = trim((string)($_POST['db_host'] ?? ''));
        $dbPort = trim((string)($_POST['db_port'] ?? ''));
        $dbName = trim((string)($_POST['db_name'] ?? ''));
        $dbUser = trim((string)($_POST['db_user'] ?? ''));
        $dbPass = (string)($_POST['db_pass'] ?? '');

        $tenantName = trim((string)($_POST['tenant_name'] ?? ''));
        $adminEmail = trim((string)($_POST['admin_email'] ?? ''));
        $adminName = trim((string)($_POST['admin_name'] ?? ''));
        $adminPass = (string)($_POST['admin_password'] ?? '');

        $licenseKey = trim((string)($_POST['license_key'] ?? ''));

        $_SESSION['_old_install'] = [
            'db_host' => $dbHost,
            'db_port' => $dbPort,
            'db_name' => $dbName,
            'db_user' => $dbUser,
            'db_pass' => $dbPass,
            'tenant_name' => $tenantName,
            'admin_email' => $adminEmail,
            'admin_name' => $adminName,
            'license_key' => $licenseKey,
        ];

        if ($dbHost === '' || $dbPort === '' || $dbName === '' || $dbUser === '') {
            Session::flash('error', 'Paramètres base de données incomplets.');
            redirect('/install');
        }
        if ($tenantName === '') {
            Session::flash('error', 'Nom de l’association requis.');
            redirect('/install');
        }
        if ($adminEmail === '' || $adminPass === '') {
            Session::flash('error', 'Email et mot de passe admin requis.');
            redirect('/install');
        }
        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Email admin invalide.');
            redirect('/install');
        }
        if (strlen($adminPass) < 8) {
            Session::flash('error', 'Mot de passe admin: 8 caractères minimum.');
            redirect('/install');
        }
        if ($licenseKey === '') {
            Session::flash('error', 'Clé de licence requise.');
            redirect('/install');
        }

        try {
            $pdo = Installer::pdoFromParams($dbHost, $dbPort, $dbName, $dbUser, $dbPass);
        } catch (\Throwable $e) {
            Session::flash('error', 'Connexion DB impossible: ' . $e->getMessage());
            redirect('/install');
        }

        if (Installer::hasTenantsTable($pdo)) {
            Session::flash('error', 'La base semble déjà initialisée (table tenants existante). Utilise une base vide ou supprime les tables existantes, puis relance /install.');
            redirect('/install');
        }

        $mig = Installer::runMigrations($pdo);
        if (!$mig['ok']) {
            Session::flash('error', 'Migrations échouées: ' . (string)$mig['error']);
            redirect('/install');
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('INSERT INTO tenants (name) VALUES (:name)');
            $stmt->execute(['name' => $tenantName]);
            $tenantId = (int)$pdo->lastInsertId();

            $hash = password_hash($adminPass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (tenant_id, email, password_hash, full_name, is_active, is_admin) VALUES (:tenant_id, :email, :password_hash, :full_name, 1, 1)');
            $stmt->execute([
                'tenant_id' => $tenantId,
                'email' => $adminEmail,
                'password_hash' => $hash,
                'full_name' => ($adminName !== '' ? $adminName : null),
            ]);

            $pdo->exec("INSERT IGNORE INTO modules (module_key, name) VALUES ('treasury', 'Trésorerie')");
            $pdo->exec("INSERT IGNORE INTO modules (module_key, name) VALUES ('drive', 'Google Drive')");

            $stmt = $pdo->prepare("INSERT IGNORE INTO tenant_modules (tenant_id, module_id, is_enabled, enabled_at)
                SELECT :tenant_id, m.id, 1, CURRENT_TIMESTAMP
                FROM modules m
                WHERE m.module_key = 'treasury'
                LIMIT 1");
            $stmt->execute(['tenant_id' => $tenantId]);

            $stmt = $pdo->prepare("INSERT INTO tenant_licenses (tenant_id, license_key, status)
                VALUES (:tenant_id, :license_key, 'unknown')
                ON DUPLICATE KEY UPDATE license_key = VALUES(license_key), status = 'unknown', last_error = NULL");
            $stmt->execute([
                'tenant_id' => $tenantId,
                'license_key' => $licenseKey,
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Session::flash('error', 'Création admin échouée: ' . $e->getMessage());
            redirect('/install');
        }

        Installer::writeEnv([
            'DB_HOST' => $dbHost,
            'DB_PORT' => $dbPort,
            'DB_NAME' => $dbName,
            'DB_USER' => $dbUser,
            'DB_PASS' => $dbPass,
        ]);

        Installer::lock();
        unset($_SESSION['_old_install']);

        Session::flash('success', 'Installation terminée. Connecte-toi avec ton compte admin.');
        redirect('/login');
    }
}
