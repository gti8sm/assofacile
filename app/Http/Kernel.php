<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Routing\Router;
use App\Database\Db;
use App\Support\Installer;
use App\Support\Migrator;

final class Kernel
{
    public function handle(): void
    {
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        if (!Installer::isLocked() && $path !== '/install') {
            redirect('/install');
        }

        if (Installer::isLocked()) {
            $allowed = [
                '/login',
                '/logout',
                '/admin/update',
            ];

            try {
                $pdo = Db::pdo();
                $pending = Migrator::pending($pdo);
            } catch (\Throwable $e) {
                $pending = [];
            }

            if (!empty($pending) && !in_array($path, $allowed, true)) {
                if (!isset($_SESSION['user_id'])) {
                    redirect('/login');
                }

                if (isset($_SESSION['is_admin']) && (int)$_SESSION['is_admin'] === 1) {
                    redirect('/admin/update');
                }

                http_response_code(503);
                header('Content-Type: text/html; charset=utf-8');
                echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Mise à jour requise</title></head><body style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f8fafc;color:#0f172a;">
                    <div style="max-width:720px;margin:40px auto;padding:24px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;">
                        <h1 style="margin:0 0 12px;font-size:22px;">Mise à jour requise</h1>
                        <p style="margin:0 0 10px;">L\'application doit appliquer une mise à jour de base de données.</p>
                        <p style="margin:0;">Merci de contacter un administrateur pour lancer la mise à jour.</p>
                    </div>
                </body></html>';
                return;
            }
        }

        $router = new Router();
        require base_path('routes/web.php');
        $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
    }
}
