<?php

declare(strict_types=1);

namespace Licensing\Http\Controllers;

use Licensing\Database\Db;
use Licensing\Support\Env;
use Licensing\Support\Installer;
use Licensing\Support\LicenseKey;
use Licensing\Support\Session;

final class LicensesController
{
    private static function requireAdmin(): void
    {
        if (!Installer::isLocked()) {
            redirect('/install');
        }

        if (!isset($_SESSION['admin_id'])) {
            redirect('/login');
        }
    }

    public static function index(): void
    {
        self::requireAdmin();

        $pdo = Db::pdo();
        $licenses = $pdo->query('SELECT * FROM licenses ORDER BY created_at DESC')->fetchAll();

        $flash = Session::flash('success');
        $error = Session::flash('error');

        require base_path('views/licenses/index.php');
    }

    public static function store(): void
    {
        self::requireAdmin();

        $licenseKey = trim((string)($_POST['license_key'] ?? ''));
        $tenantName = trim((string)($_POST['tenant_name'] ?? ''));
        $tenantEmail = trim((string)($_POST['tenant_email'] ?? ''));
        $planType = trim((string)($_POST['plan_type'] ?? ''));
        $validUntil = trim((string)($_POST['valid_until'] ?? ''));

        if ($planType !== 'annual' && $planType !== 'lifetime') {
            Session::flash('error', 'Données invalides.');
            redirect('/licenses');
        }

        if ($tenantEmail !== '' && filter_var($tenantEmail, FILTER_VALIDATE_EMAIL) === false) {
            Session::flash('error', 'Email invalide.');
            redirect('/licenses');
        }

        if ($planType === 'annual' && $validUntil === '') {
            Session::flash('error', 'valid_until requis pour un abo annuel.');
            redirect('/licenses');
        }

        if ($planType === 'lifetime') {
            $validUntil = '';
        }

        if ($licenseKey === '') {
            $licenseKey = LicenseKey::generate();
        }

        $pdo = Db::pdo();

        $created = false;
        $attempts = 0;
        while (!$created && $attempts < 5) {
            $attempts++;
            try {
                $stmt = $pdo->prepare('INSERT INTO licenses (license_key, tenant_name, plan_type, valid_until) VALUES (:license_key, :tenant_name, :plan_type, :valid_until)');
                $stmt->execute([
                    'license_key' => $licenseKey,
                    'tenant_name' => ($tenantName !== '' ? $tenantName : null),
                    'plan_type' => $planType,
                    'valid_until' => ($validUntil !== '' ? $validUntil : null),
                ]);
                $created = true;
            } catch (\PDOException $e) {
                if ((string)$e->getCode() === '23000') {
                    $licenseKey = LicenseKey::generate();
                    continue;
                }
                throw $e;
            }
        }

        if (!$created) {
            Session::flash('error', 'Impossible de générer une clé unique.');
            redirect('/licenses');
        }

        $emailMsg = '';
        if ($tenantEmail !== '') {
            $host = (string)($_SERVER['HTTP_HOST'] ?? 'licences.assofacile.fr');
            $from = 'no-reply@' . preg_replace('/[^a-z0-9\.-]/i', '', $host);
            $subject = 'Votre licence AssoFacile';

            $body = "Bonjour\n\n";
            $body .= "Voici votre clé de licence AssoFacile :\n\n";
            $body .= $licenseKey . "\n\n";
            $body .= "Type : {$planType}\n";
            if ($planType === 'annual') {
                $body .= "Valide jusqu'au : {$validUntil}\n";
            }
            $body .= "\n";
            $body .= "Serveur de licences : https://" . $host . "\n";

            $headers = "From: {$from}\r\n";
            $headers .= "Reply-To: {$from}\r\n";

            $sent = @mail($tenantEmail, $subject, $body, $headers);
            $emailMsg = $sent ? ' Email envoyé.' : ' Email non envoyé.';
        }

        Session::flash('success', 'Licence créée.' . $emailMsg);
        redirect('/licenses');
    }

    public static function generateKey(): void
    {
        self::requireAdmin();

        header('Content-Type: application/json');
        echo json_encode(['license_key' => LicenseKey::generate()]);
    }

    public static function renew(): void
    {
        self::requireAdmin();

        $id = (int)($_POST['id'] ?? 0);
        $validUntil = trim((string)($_POST['valid_until'] ?? ''));
        if ($id <= 0 || $validUntil === '') {
            Session::flash('error', 'Renouvellement invalide.');
            redirect('/licenses');
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('UPDATE licenses SET valid_until = :valid_until, is_revoked = 0, revoked_at = NULL WHERE id = :id');
        $stmt->execute(['id' => $id, 'valid_until' => $validUntil]);

        Session::flash('success', 'Licence renouvelée.');
        redirect('/licenses');
    }

    public static function revoke(): void
    {
        self::requireAdmin();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            Session::flash('error', 'Révocation invalide.');
            redirect('/licenses');
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('UPDATE licenses SET is_revoked = 1, revoked_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);

        Session::flash('success', 'Licence révoquée.');
        redirect('/licenses');
    }
}
