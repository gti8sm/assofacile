<?php

declare(strict_types=1);

namespace Licensing\Http\Controllers;

use Licensing\Support\Installer;
use Licensing\Support\LicenseToken;
use Licensing\Support\Session;

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
            'db_name' => 'assofacile_licensing',
            'db_user' => 'root',
            'db_pass' => '',
            'admin_email' => '',
            'admin_name' => '',
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

        $adminEmail = trim((string)($_POST['admin_email'] ?? ''));
        $adminName = trim((string)($_POST['admin_name'] ?? ''));
        $adminPass = (string)($_POST['admin_password'] ?? '');

        $_SESSION['_old_install'] = [
            'db_host' => $dbHost,
            'db_port' => $dbPort,
            'db_name' => $dbName,
            'db_user' => $dbUser,
            'db_pass' => $dbPass,
            'admin_email' => $adminEmail,
            'admin_name' => $adminName,
        ];

        if ($dbHost === '' || $dbPort === '' || $dbName === '' || $dbUser === '') {
            Session::flash('error', 'Paramètres base de données incomplets.');
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

        try {
            $pdo = Installer::pdoFromParams($dbHost, $dbPort, $dbName, $dbUser, $dbPass);
        } catch (\Throwable $e) {
            Session::flash('error', 'Connexion DB impossible: ' . $e->getMessage());
            redirect('/install');
        }

        $mig = Installer::runMigrations($pdo);
        if (!$mig['ok']) {
            Session::flash('error', 'Migrations échouées: ' . (string)$mig['error']);
            redirect('/install');
        }

        try {
            $pdo->beginTransaction();

            $hash = password_hash($adminPass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO admins (email, password_hash, full_name, is_active) VALUES (:email, :password_hash, :full_name, 1)');
            $stmt->execute([
                'email' => $adminEmail,
                'password_hash' => $hash,
                'full_name' => ($adminName !== '' ? $adminName : null),
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Session::flash('error', 'Création admin échouée: ' . $e->getMessage());
            redirect('/install');
        }

        $keys = LicenseToken::generateKeypair();

        Installer::writeEnv([
            'APP_ENV' => 'prod',
            'APP_URL' => (string)($_SERVER['HTTP_HOST'] ?? 'https://licences.assofacile.fr'),
            'DB_HOST' => $dbHost,
            'DB_PORT' => $dbPort,
            'DB_NAME' => $dbName,
            'DB_USER' => $dbUser,
            'DB_PASS' => $dbPass,
            'SESSION_NAME' => 'assofacile_licensing',
            'LICENSE_PRIVATE_KEY_B64' => $keys['private_b64'],
            'LICENSE_PUBLIC_KEY_B64' => $keys['public_b64'],
        ]);

        Installer::lock();
        unset($_SESSION['_old_install']);

        Session::flash('success', 'Installation terminée.');
        redirect('/login');
    }
}
