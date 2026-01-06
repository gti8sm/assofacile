<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Db;

final class PublicSiteController
{
    public static function tenantHome(string $tenant): void
    {
        self::show($tenant, null);
    }

    public static function tenantPage(string $tenant, string $page): void
    {
        self::show($tenant, $page);
    }

    private static function show(string $tenant, ?string $pageSlug): void
    {
        $tenantKey = trim($tenant);
        if ($tenantKey === '') {
            self::notFound('', $pageSlug, 'tenant_key_empty');
            return;
        }

        $isPreview = isset($_GET['preview']) && (string)($_GET['preview'] ?? '') === '1'
            && isset($_SESSION['is_admin'], $_SESSION['tenant_id'])
            && (int)$_SESSION['is_admin'] === 1;

        $pdo = Db::pdo();

        $tenantRow = null;
        if (ctype_digit($tenantKey)) {
            $stmt = $pdo->prepare('SELECT id, name, slug FROM tenants WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => (int)$tenantKey]);
            $tenantRow = $stmt->fetch();
        } else {
            $stmt = $pdo->prepare('SELECT id, name, slug FROM tenants WHERE slug = :slug LIMIT 1');
            $stmt->execute(['slug' => $tenantKey]);
            $tenantRow = $stmt->fetch();
        }

        if (!$tenantRow) {
            self::notFound('', $pageSlug, 'tenant_not_found');
            return;
        }

        $tenantId = (int)$tenantRow['id'];

        $pageRow = null;
        if ($pageSlug === null || $pageSlug === '') {
            if ($isPreview) {
                $stmt = $pdo->prepare('SELECT id, title, published_revision_id FROM public_pages WHERE tenant_id = :tenant_id AND is_home = 1 LIMIT 1');
                $stmt->execute(['tenant_id' => $tenantId]);
                $pageRow = $stmt->fetch();
            } else {
                $stmt = $pdo->prepare('SELECT id, title, published_revision_id FROM public_pages WHERE tenant_id = :tenant_id AND is_home = 1 AND is_published = 1 LIMIT 1');
                $stmt->execute(['tenant_id' => $tenantId]);
                $pageRow = $stmt->fetch();
            }
        } else {
            if ($isPreview) {
                $stmt = $pdo->prepare('SELECT id, title, published_revision_id FROM public_pages WHERE tenant_id = :tenant_id AND slug = :slug LIMIT 1');
                $stmt->execute(['tenant_id' => $tenantId, 'slug' => $pageSlug]);
                $pageRow = $stmt->fetch();
            } else {
                $stmt = $pdo->prepare('SELECT id, title, published_revision_id FROM public_pages WHERE tenant_id = :tenant_id AND slug = :slug AND is_published = 1 LIMIT 1');
                $stmt->execute(['tenant_id' => $tenantId, 'slug' => $pageSlug]);
                $pageRow = $stmt->fetch();
            }
        }

        if (!$pageRow) {
            http_response_code(404);
            self::notFound((string)($tenantRow['name'] ?? ''), $pageSlug, $pageSlug ? 'page_not_found_or_unpublished' : 'home_not_found_or_unpublished');
            return;
        }

        $revId = $pageRow['published_revision_id'] ?? null;
        if ($revId === null && $isPreview) {
            $stmt = $pdo->prepare('SELECT id FROM public_page_revisions WHERE tenant_id = :tenant_id AND page_id = :page_id ORDER BY id DESC LIMIT 1');
            $stmt->execute(['tenant_id' => $tenantId, 'page_id' => (int)$pageRow['id']]);
            $rev = $stmt->fetch();
            if ($rev) {
                $revId = (int)$rev['id'];
            }
        }

        if ($revId === null) {
            http_response_code(404);
            self::notFound((string)($tenantRow['name'] ?? ''), $pageSlug, 'published_revision_id_null');
            return;
        }

        $stmt = $pdo->prepare('SELECT content_json FROM public_page_revisions WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
        $stmt->execute(['id' => (int)$revId, 'tenant_id' => $tenantId]);
        $revRow = $stmt->fetch();
        if (!$revRow) {
            http_response_code(404);
            self::notFound((string)($tenantRow['name'] ?? ''), $pageSlug, 'published_revision_not_found');
            return;
        }

        $contentRaw = (string)($revRow['content_json'] ?? '');
        $content = json_decode($contentRaw, true);
        if (!is_array($content)) {
            $content = [];
        }

        $title = (string)($pageRow['title'] ?? $tenantRow['name']);
        $tenantName = (string)($tenantRow['name'] ?? '');

        require base_path('views/public_site/show.php');
    }

    private static function notFound(string $tenantName, ?string $pageSlug, string $reason): void
    {
        http_response_code(404);
        $requestedPath = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $debugReason = $reason;
        require base_path('views/public_site/404.php');
    }
}
