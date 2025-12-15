<?php

declare(strict_types=1);

namespace Licensing\Http\Controllers;

use Licensing\Support\Installer;
use Licensing\Support\LicenseToken;
use Licensing\Support\Session;

final class InstallController
{
    public static function generateKeys(): void
    {
        if (Installer::isLocked()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Install locked.']);
            return;
        }

        try {
            $keys = LicenseToken::generateKeypair();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true] + $keys);
        } catch (\Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

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
            'license_private_key_b64' => '',
            'license_public_key_b64' => '',
            'notify_email' => '',
            'send_keys_email' => '',
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

        $privB64 = trim((string)($_POST['license_private_key_b64'] ?? ''));
        $pubB64 = trim((string)($_POST['license_public_key_b64'] ?? ''));

        $notifyEmail = trim((string)($_POST['notify_email'] ?? ''));
        $sendKeysEmail = (string)($_POST['send_keys_email'] ?? '');

        $_SESSION['_old_install'] = [
            'db_host' => $dbHost,
            'db_port' => $dbPort,
            'db_name' => $dbName,
            'db_user' => $dbUser,
            'db_pass' => $dbPass,
            'admin_email' => $adminEmail,
            'admin_name' => $adminName,
            'license_private_key_b64' => $privB64,
            'license_public_key_b64' => $pubB64,
            'notify_email' => $notifyEmail,
            'send_keys_email' => ($sendKeysEmail !== '' ? '1' : ''),
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

        if (($sendKeysEmail !== '') && ($notifyEmail === '' || !filter_var($notifyEmail, FILTER_VALIDATE_EMAIL))) {
            Session::flash('error', 'Email de notification invalide.');
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

        if ($privB64 === '' && $pubB64 === '') {
            $keys = LicenseToken::generateKeypair();
            $privB64 = $keys['private_b64'];
            $pubB64 = $keys['public_b64'];
        }

        if ($privB64 !== '') {
            $priv = base64_decode($privB64, true);
            if ($priv === false || strlen($priv) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
                Session::flash('error', 'Clé privée invalide (base64).');
                redirect('/install');
            }

            if ($pubB64 === '' && function_exists('sodium_crypto_sign_publickey_from_secretkey')) {
                $pub = sodium_crypto_sign_publickey_from_secretkey($priv);
                $pubB64 = base64_encode($pub);
            }
        }

        if ($pubB64 === '') {
            Session::flash('error', 'Clé publique manquante. Clique "Générer" pour obtenir une paire de clés.');
            redirect('/install');
        }

        $pub = base64_decode($pubB64, true);
        if ($pub === false || strlen($pub) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            Session::flash('error', 'Clé publique invalide (base64).');
            redirect('/install');
        }

        Installer::writeEnv([
            'APP_ENV' => 'prod',
            'APP_URL' => (string)($_SERVER['HTTP_HOST'] ?? 'https://licences.assofacile.fr'),
            'DB_HOST' => $dbHost,
            'DB_PORT' => $dbPort,
            'DB_NAME' => $dbName,
            'DB_USER' => $dbUser,
            'DB_PASS' => $dbPass,
            'SESSION_NAME' => 'assofacile_licensing',
            'LICENSE_PRIVATE_KEY_B64' => $privB64,
            'LICENSE_PUBLIC_KEY_B64' => $pubB64,
        ]);

        if ($sendKeysEmail !== '') {
            $subject = 'AssoFacile - Clé publique du serveur de licences';
            $body = "Voici la clé publique (Ed25519) à copier dans le .env de l'application AssoFacile:\n\n";
            $body .= "LICENSE_PUBLIC_KEY=\"{$pubB64}\"\n\n";
            $body .= "Conserve la clé privée sur le serveur de licences uniquement (ne pas partager).\n";
            $headers = "Content-Type: text/plain; charset=UTF-8\r\n";
            @mail($notifyEmail, $subject, $body, $headers);
        }

        Installer::lock();
        unset($_SESSION['_old_install']);

        Session::flash('success', 'Installation terminée.');
        redirect('/login');
    }
}
