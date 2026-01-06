<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Db;
use App\Support\Env;
use App\Support\License;
use App\Support\Session;

final class AdminLicenseController
{
    private static function requireAdmin(): void
    {
        if (!isset($_SESSION['user_id'], $_SESSION['tenant_id'])) {
            redirect('/login');
        }

        if (!isset($_SESSION['is_admin']) || (int)$_SESSION['is_admin'] !== 1) {
            http_response_code(403);
            echo '403';
            exit;
        }
    }

    public static function index(): void
    {
        self::requireAdmin();

        $tenantId = (int)$_SESSION['tenant_id'];
        $license = License::getLicenseRow($tenantId);

        $portalUrl = null;
        if (is_array($license)) {
            $token = trim((string)($license['signed_token'] ?? ''));
            if ($token !== '') {
                $server = Env::get('LICENSE_SERVER_URL', 'https://licences.assofacile.net');
                $server = rtrim((string)$server, '/');
                $portalUrl = $server . '/portal?token=' . urlencode($token);
            }
        }

        $pdo = Db::pdo();

        $tiersLimit = 50;
        $tiersUsed = 0;
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) AS c FROM tiers WHERE tenant_id = :tenant_id');
            $stmt->execute(['tenant_id' => $tenantId]);
            $row = $stmt->fetch();
            $tiersUsed = (int)($row['c'] ?? 0);
        } catch (\Throwable $e) {
            $tiersUsed = 0;
        }

        $storageLimitBytes = 2 * 1024 * 1024 * 1024;
        $storageUsedBytes = 0;
        try {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(size_bytes), 0) AS s FROM treasury_attachments WHERE tenant_id = :tenant_id AND storage_driver = 'local'");
            $stmt->execute(['tenant_id' => $tenantId]);
            $row = $stmt->fetch();
            $storageUsedBytes = (int)($row['s'] ?? 0);
        } catch (\Throwable $e) {
            $storageUsedBytes = 0;
        }

        $extraTiers = max(0, $tiersUsed - $tiersLimit);
        $overageBlocks = $extraTiers > 0 ? (int)ceil($extraTiers / 10) : 0;
        $overageEur = $overageBlocks * 5;

        $flash = Session::flash('success');
        $error = Session::flash('error');

        require base_path('views/admin/license.php');
    }

    public static function update(): void
    {
        self::requireAdmin();

        $tenantId = (int)$_SESSION['tenant_id'];
        $key = trim((string)($_POST['license_key'] ?? ''));

        if ($key === '') {
            Session::flash('error', 'Clé de licence requise.');
            redirect('/admin/license');
        }

        License::upsertKey($tenantId, $key);

        $res = License::validateOnline($tenantId, $_SERVER['HTTP_HOST'] ?? null, null);
        if ($res['ok']) {
            Session::flash('success', 'Licence validée.');
        } else {
            $status = (string)($res['status'] ?? 'error');
            $msg = trim((string)($res['message'] ?? ''));
            Session::flash('error', 'Licence: ' . $status . ($msg !== '' ? ' - ' . $msg : ''));
        }

        redirect('/admin/license');
    }
}
