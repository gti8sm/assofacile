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

        // Tenant-in-path routing (/t/{tenantSlug}/...) - rewrite to existing routes for backward compatibility.
        if (preg_match('~^/t/([^/]+)(/.*)?$~', $path, $m)) {
            $tenantSlug = trim((string)($m[1] ?? ''));
            $rest = (string)($m[2] ?? '');
            if ($rest === '') {
                $rest = '/';
            }

            if ($tenantSlug === '') {
                http_response_code(404);
                echo '404';
                return;
            }

            try {
                $pdo = Db::pdo();
                if (ctype_digit($tenantSlug)) {
                    $stmt = $pdo->prepare('SELECT id, slug, name FROM tenants WHERE id = :id LIMIT 1');
                    $stmt->execute(['id' => (int)$tenantSlug]);
                } else {
                    $stmt = $pdo->prepare('SELECT id, slug, name FROM tenants WHERE slug = :slug LIMIT 1');
                    $stmt->execute(['slug' => $tenantSlug]);
                }
                $tenantRow = $stmt->fetch();
            } catch (\Throwable $e) {
                $tenantRow = null;
            }

            if (!$tenantRow) {
                http_response_code(404);
                echo '404';
                return;
            }

            $tenantId = (int)($tenantRow['id'] ?? 0);
            if ($tenantId <= 0) {
                http_response_code(404);
                echo '404';
                return;
            }

            // If user is logged-in, enforce that the URL tenant matches the session tenant.
            if (isset($_SESSION['user_id'], $_SESSION['tenant_id'])) {
                if ((int)$_SESSION['tenant_id'] !== $tenantId) {
                    http_response_code(403);
                    echo '403';
                    return;
                }
            }

            $_SESSION['tenant_id'] = $tenantId;
            $_SESSION['tenant_slug'] = (string)($tenantRow['slug'] ?? $tenantSlug);
            $_SESSION['tenant_name'] = (string)($tenantRow['name'] ?? '');

            if ($rest === '/' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
                redirect('/t/' . $_SESSION['tenant_slug'] . '/dashboard');
            }

            // Rewrite the request URI to the legacy route path.
            $query = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY);
            $newUri = $rest;
            if ($query) {
                $newUri .= '?' . $query;
            }
            $_SERVER['REQUEST_URI'] = $newUri;
            $path = $rest;
        } elseif (($method = ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET') {
            // Redirect legacy tenantless internal URLs to the tenant-prefixed version for clarity.
            $tenantSlug = isset($_SESSION['tenant_slug']) ? trim((string)$_SESSION['tenant_slug']) : '';
            $isInternal = !str_starts_with($path, '/s/')
                && !str_starts_with($path, '/t/')
                && !in_array($path, ['/login', '/install'], true)
                && $path !== '/';

            if ($tenantSlug !== '' && $isInternal) {
                $query = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY);
                $to = '/t/' . $tenantSlug . $path;
                if (is_string($query) && $query !== '') {
                    $to .= '?' . $query;
                }
                redirect($to);
            }
        }
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

        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        $host = strtolower(trim($host));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;

        if ($host !== '' && $host !== 'localhost' && $host !== '127.0.0.1') {
            $isWww = str_starts_with($host, 'www.');
            $lookupHost = $isWww ? substr($host, 4) : $host;

            try {
                $pdo = Db::pdo();
                $stmt = $pdo->prepare('SELECT id, slug FROM tenants WHERE public_domain = :domain AND public_domain_status = "verified" LIMIT 1');
                $stmt->execute(['domain' => $lookupHost]);
                $tenantRow = $stmt->fetch();
            } catch (\Throwable $e) {
                $tenantRow = null;
            }

            if ($tenantRow) {
                if ($isWww) {
                    $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    header('Location: ' . $scheme . '://' . $lookupHost . $uri, true, 301);
                    return;
                }

                $tenantKey = trim((string)($tenantRow['slug'] ?? ''));
                if ($tenantKey === '') {
                    $tenantKey = (string)((int)($tenantRow['id'] ?? 0));
                }

                if ($tenantKey !== '') {
                    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
                    if ($path === '/' || $path === '') {
                        $_SERVER['REQUEST_URI'] = '/s/' . $tenantKey;
                    } elseif (preg_match('~^/([^/]+)$~', $path, $m)) {
                        $_SERVER['REQUEST_URI'] = '/s/' . $tenantKey . '/' . (string)$m[1];
                    }
                }
            }
        }

        $router = new Router();
        require base_path('routes/web.php');
        $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
    }
}
