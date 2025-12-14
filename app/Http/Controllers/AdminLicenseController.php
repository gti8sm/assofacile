<?php

declare(strict_types=1);

namespace App\Http\Controllers;

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
            Session::flash('error', 'Licence: ' . (string)($res['status'] ?? 'error'));
        }

        redirect('/admin/license');
    }
}
