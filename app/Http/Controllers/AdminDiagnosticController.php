<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Env;
use App\Support\GoogleDrive;
use App\Support\License;

final class AdminDiagnosticController
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

        $google = [
            'client_id' => (string)(Env::get('GOOGLE_CLIENT_ID') ?? ''),
            'client_secret' => (string)(Env::get('GOOGLE_CLIENT_SECRET') ?? ''),
            'redirect_uri' => (string)(Env::get('GOOGLE_REDIRECT_URI') ?? ''),
        ];

        $driveAvailable = GoogleDrive::isAvailable();
        $driveConfigured = GoogleDrive::isConfigured() && $driveAvailable;
        $driveConnected = $driveConfigured && GoogleDrive::isConnected($tenantId);
        $driveFolderId = $driveConnected ? GoogleDrive::getDriveFolderId($tenantId) : null;

        $license = License::getLicenseRow($tenantId);

        require base_path('views/admin/diagnostic.php');
    }

    public static function envTemplate(): void
    {
        self::requireAdmin();

        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $defaultRedirect = $host !== '' ? ($scheme . '://' . $host . '/drive/callback') : '';

        $content = "GOOGLE_CLIENT_ID=\n";
        $content .= "GOOGLE_CLIENT_SECRET=\n";
        $content .= "GOOGLE_REDIRECT_URI=" . $defaultRedirect . "\n";

        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename=".env.template"');
        echo $content;
    }
}
