<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Db;
use App\Support\Modules;
use App\Support\Session;

final class AdminPublicSitePagesController
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

    private static function requireModuleEnabled(int $tenantId): void
    {
        if (!Modules::isEnabled($tenantId, 'public_site')) {
            Session::flash('error', 'Module désactivé.');
            redirect('/admin/modules');
        }
    }

    public static function index(): void
    {
        self::requireAdmin();

        $tenantId = (int)$_SESSION['tenant_id'];
        self::requireModuleEnabled($tenantId);

        try {
            $pdo = Db::pdo();
            $tenant = $pdo->prepare('SELECT id, name, slug FROM tenants WHERE id = :id LIMIT 1');
            $tenant->execute(['id' => $tenantId]);
            $tenantRow = $tenant->fetch();
        } catch (\Throwable $e) {
            Session::flash('error', 'Impossible de charger le module Site public (migration public_site manquante ? Lance /admin/update).');
            redirect('/admin/update');
        }
        if (!$tenantRow) {
            http_response_code(404);
            echo '404';
            return;
        }

        try {
            $pages = $pdo->prepare('SELECT id, slug, title, is_home, is_published, published_revision_id, updated_at FROM public_pages WHERE tenant_id = :tenant_id ORDER BY is_home DESC, updated_at DESC, id DESC');
            $pages->execute(['tenant_id' => $tenantId]);
            $rows = $pages->fetchAll();
        } catch (\Throwable $e) {
            Session::flash('error', 'Impossible de charger le module Site public (migration public_site manquante ? Lance /admin/update).');
            redirect('/admin/update');
        }

        $flash = Session::flash('success');
        $error = Session::flash('error');

        require base_path('views/admin/public_site_pages/index.php');
    }

    public static function create(): void
    {
        self::requireAdmin();

        $tenantId = (int)$_SESSION['tenant_id'];
        self::requireModuleEnabled($tenantId);

        try {
            $pdo = Db::pdo();
            $tenant = $pdo->prepare('SELECT id, name, slug FROM tenants WHERE id = :id LIMIT 1');
            $tenant->execute(['id' => $tenantId]);
            $tenantRow = $tenant->fetch();
        } catch (\Throwable $e) {
            Session::flash('error', 'Impossible de charger le module Site public (migration public_site manquante ? Lance /admin/update).');
            redirect('/admin/update');
        }
        if (!$tenantRow) {
            http_response_code(404);
            echo '404';
            return;
        }

        $page = [
            'id' => null,
            'slug' => '',
            'title' => '',
            'is_home' => 0,
            'is_published' => 0,
            'published_revision_id' => null,
        ];

        $contentJson = json_encode([
            'schema' => 2,
            'sections' => [
                [
                    'layout' => '1col',
                    'columns' => [
                        [
                            [
                                'type' => 'text',
                                'props' => [
                                    'title' => (string)($tenantRow['name'] ?? ''),
                                    'body' => "Notre site est en cours de construction.\n\nBienvenue !",
                                ],
                            ],
                            [
                                'type' => 'cta',
                                'props' => [
                                    'label' => 'Se connecter',
                                    'href' => '/login',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $flash = Session::flash('success');
        $error = Session::flash('error');

        require base_path('views/admin/public_site_pages/edit.php');
    }

    public static function edit(): void
    {
        self::requireAdmin();

        $tenantId = (int)$_SESSION['tenant_id'];
        self::requireModuleEnabled($tenantId);

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(404);
            echo '404';
            return;
        }

        try {
            $pdo = Db::pdo();
            $tenant = $pdo->prepare('SELECT id, name, slug FROM tenants WHERE id = :id LIMIT 1');
            $tenant->execute(['id' => $tenantId]);
            $tenantRow = $tenant->fetch();
        } catch (\Throwable $e) {
            Session::flash('error', 'Impossible de charger le module Site public (migration public_site manquante ? Lance /admin/update).');
            redirect('/admin/update');
        }
        if (!$tenantRow) {
            http_response_code(404);
            echo '404';
            return;
        }

        try {
            $stmt = $pdo->prepare('SELECT id, slug, title, is_home, is_published, published_revision_id FROM public_pages WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
            $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
            $page = $stmt->fetch();
        } catch (\Throwable $e) {
            Session::flash('error', 'Impossible de charger le module Site public (migration public_site manquante ? Lance /admin/update).');
            redirect('/admin/update');
        }
        if (!$page) {
            http_response_code(404);
            echo '404';
            return;
        }

        $revId = $page['published_revision_id'] ?? null;
        $contentJson = '';
        if ($revId !== null) {
            $stmt = $pdo->prepare('SELECT content_json FROM public_page_revisions WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
            $stmt->execute(['id' => (int)$revId, 'tenant_id' => $tenantId]);
            $revRow = $stmt->fetch();
            if ($revRow) {
                $decoded = json_decode((string)($revRow['content_json'] ?? ''), true);
                if (is_array($decoded)) {
                    $decoded = self::normalizeContent($decoded);
                    $contentJson = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                } else {
                    $contentJson = (string)($revRow['content_json'] ?? '');
                }
            }
        }

        if ($contentJson === '') {
            $contentJson = json_encode(['schema' => 2, 'sections' => []], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        $flash = Session::flash('success');
        $error = Session::flash('error');

        require base_path('views/admin/public_site_pages/edit.php');
    }

    public static function save(): void
    {
        self::requireAdmin();

        $tenantId = (int)$_SESSION['tenant_id'];
        self::requireModuleEnabled($tenantId);

        $id = (int)($_POST['id'] ?? 0);
        $slug = trim((string)($_POST['slug'] ?? ''));
        $title = trim((string)($_POST['title'] ?? ''));
        $isHome = isset($_POST['is_home']);

        $contentJson = (string)($_POST['content_json'] ?? '[]');
        $decoded = json_decode($contentJson, true);
        if (!is_array($decoded)) {
            Session::flash('error', 'JSON invalide.');
            if ($id > 0) {
                redirect('/admin/public-site/pages/edit?id=' . $id);
            }
            redirect('/admin/public-site/pages/new');
        }

        $decoded = self::normalizeContent($decoded);
        if (!isset($decoded['sections']) || !is_array($decoded['sections'])) {
            Session::flash('error', 'Contenu invalide (sections manquantes).');
            if ($id > 0) {
                redirect('/admin/public-site/pages/edit?id=' . $id);
            }
            redirect('/admin/public-site/pages/new');
        }

        if ($slug === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            Session::flash('error', 'Slug invalide (minuscules, chiffres, tirets).');
            if ($id > 0) {
                redirect('/admin/public-site/pages/edit?id=' . $id);
            }
            redirect('/admin/public-site/pages/new');
        }

        if ($title === '') {
            Session::flash('error', 'Titre requis.');
            if ($id > 0) {
                redirect('/admin/public-site/pages/edit?id=' . $id);
            }
            redirect('/admin/public-site/pages/new');
        }

        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            if ($isHome) {
                $pdo->prepare('UPDATE public_pages SET is_home = 0 WHERE tenant_id = :tenant_id')->execute(['tenant_id' => $tenantId]);
            }

            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE public_pages SET slug = :slug, title = :title, is_home = :is_home WHERE id = :id AND tenant_id = :tenant_id');
                $stmt->execute([
                    'slug' => $slug,
                    'title' => $title,
                    'is_home' => $isHome ? 1 : 0,
                    'id' => $id,
                    'tenant_id' => $tenantId,
                ]);
                $pageId = $id;
            } else {
                $stmt = $pdo->prepare('INSERT INTO public_pages (tenant_id, slug, title, is_home, is_published) VALUES (:tenant_id, :slug, :title, :is_home, 0)');
                $stmt->execute([
                    'tenant_id' => $tenantId,
                    'slug' => $slug,
                    'title' => $title,
                    'is_home' => $isHome ? 1 : 0,
                ]);
                $pageId = (int)$pdo->lastInsertId();
            }

            $stmt = $pdo->prepare('INSERT INTO public_page_revisions (tenant_id, page_id, schema_version, content_json, created_by_user_id) VALUES (:tenant_id, :page_id, 1, :content_json, :user_id)');
            $stmt->execute([
                'tenant_id' => $tenantId,
                'page_id' => $pageId,
                'content_json' => json_encode($decoded, JSON_UNESCAPED_UNICODE),
                'user_id' => (int)($_SESSION['user_id'] ?? 0) ?: null,
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Session::flash('error', 'Erreur sauvegarde: ' . $e->getMessage());
            if ($id > 0) {
                redirect('/admin/public-site/pages/edit?id=' . $id);
            }
            redirect('/admin/public-site/pages/new');
        }

        Session::flash('success', 'Brouillon sauvegardé.');
        redirect('/admin/public-site/pages/edit?id=' . (string)$pageId);
    }

    public static function publish(): void
    {
        self::requireAdmin();

        $tenantId = (int)$_SESSION['tenant_id'];
        self::requireModuleEnabled($tenantId);

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(404);
            echo '404';
            return;
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT id FROM public_pages WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $page = $stmt->fetch();
        if (!$page) {
            http_response_code(404);
            echo '404';
            return;
        }

        $stmt = $pdo->prepare('SELECT id FROM public_page_revisions WHERE page_id = :page_id AND tenant_id = :tenant_id ORDER BY id DESC LIMIT 1');
        $stmt->execute(['page_id' => $id, 'tenant_id' => $tenantId]);
        $rev = $stmt->fetch();
        if (!$rev) {
            Session::flash('error', 'Aucune révision.');
            redirect('/admin/public-site/pages/edit?id=' . $id);
        }

        $pdo->prepare('UPDATE public_pages SET published_revision_id = :rev_id, is_published = 1 WHERE id = :id AND tenant_id = :tenant_id')->execute([
            'rev_id' => (int)$rev['id'],
            'id' => $id,
            'tenant_id' => $tenantId,
        ]);

        Session::flash('success', 'Page publiée.');
        redirect('/admin/public-site/pages/edit?id=' . $id);
    }

    /** @param array<mixed> $decoded */
    private static function normalizeContent(array $decoded): array
    {
        if (isset($decoded['sections']) && is_array($decoded['sections'])) {
            if (!isset($decoded['schema'])) {
                $decoded['schema'] = 2;
            }
            return $decoded;
        }

        $isList = true;
        foreach (array_keys($decoded) as $k) {
            if (!is_int($k)) {
                $isList = false;
                break;
            }
        }

        if ($isList) {
            return [
                'schema' => 2,
                'sections' => [
                    [
                        'layout' => '1col',
                        'columns' => [
                            array_values($decoded),
                        ],
                    ],
                ],
            ];
        }

        return ['schema' => 2, 'sections' => []];
    }
}
