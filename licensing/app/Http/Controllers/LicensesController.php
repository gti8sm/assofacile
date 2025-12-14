<?php

declare(strict_types=1);

namespace Licensing\Http\Controllers;

use Licensing\Database\Db;
use Licensing\Support\Installer;
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
        $planType = trim((string)($_POST['plan_type'] ?? ''));
        $validUntil = trim((string)($_POST['valid_until'] ?? ''));

        if ($licenseKey === '' || ($planType !== 'annual' && $planType !== 'lifetime')) {
            Session::flash('error', 'Données invalides.');
            redirect('/licenses');
        }

        if ($planType === 'annual' && $validUntil === '') {
            Session::flash('error', 'valid_until requis pour un abo annuel.');
            redirect('/licenses');
        }

        if ($planType === 'lifetime') {
            $validUntil = '';
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('INSERT INTO licenses (license_key, tenant_name, plan_type, valid_until) VALUES (:license_key, :tenant_name, :plan_type, :valid_until)');
        $stmt->execute([
            'license_key' => $licenseKey,
            'tenant_name' => ($tenantName !== '' ? $tenantName : null),
            'plan_type' => $planType,
            'valid_until' => ($validUntil !== '' ? $validUntil : null),
        ]);

        Session::flash('success', 'Licence créée.');
        redirect('/licenses');
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
