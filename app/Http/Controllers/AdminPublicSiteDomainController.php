<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Db;
use App\Support\Modules;
use App\Support\Session;

final class AdminPublicSiteDomainController
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

    private static function normalizeDomain(string $domain): string
    {
        $domain = trim(strtolower($domain));
        $domain = preg_replace('~^https?://~i', '', $domain) ?? $domain;
        $domain = trim($domain, "/ ");
        $domain = preg_replace('~[:/].*$~', '', $domain) ?? $domain;
        if (str_starts_with($domain, 'www.')) {
            $domain = substr($domain, 4);
        }
        return $domain;
    }

    public static function index(): void
    {
        self::requireAdmin();

        $tenantId = (int)$_SESSION['tenant_id'];
        self::requireModuleEnabled($tenantId);

        try {
            $pdo = Db::pdo();
            $stmt = $pdo->prepare('SELECT id, name, slug, public_domain, public_domain_status, public_domain_verification_token, public_domain_verified_at FROM tenants WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $tenantId]);
            $tenantRow = $stmt->fetch();
        } catch (\Throwable $e) {
            Session::flash('error', 'Impossible de charger la configuration domaine (migration public_site manquante ? Lance /admin/update).');
            redirect('/admin/public-site/pages');
        }

        if (!$tenantRow) {
            http_response_code(404);
            echo '404';
            return;
        }

        $flash = Session::flash('success');
        $error = Session::flash('error');

        require base_path('views/admin/public_site_domain.php');
    }

    public static function save(): void
    {
        self::requireAdmin();

        $tenantId = (int)$_SESSION['tenant_id'];
        self::requireModuleEnabled($tenantId);

        $domainIn = (string)($_POST['public_domain'] ?? '');
        $domain = self::normalizeDomain($domainIn);

        if ($domain !== '' && !preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $domain)) {
            Session::flash('error', 'Domaine invalide. Exemple: monasso.fr');
            redirect('/admin/public-site/domain');
        }

        $pdo = Db::pdo();

        $token = null;
        $status = 'pending';
        $verifiedAt = null;

        if ($domain !== '') {
            try {
                $token = bin2hex(random_bytes(16));
            } catch (\Throwable $e) {
                $token = bin2hex((string)microtime(true));
            }
        } else {
            $status = 'pending';
        }

        try {
            $stmt = $pdo->prepare('UPDATE tenants SET public_domain = :public_domain, public_domain_status = :status, public_domain_verification_token = :token, public_domain_verified_at = :verified_at WHERE id = :id LIMIT 1');
            $stmt->execute([
                'public_domain' => $domain !== '' ? $domain : null,
                'status' => $domain !== '' ? $status : 'pending',
                'token' => $domain !== '' ? $token : null,
                'verified_at' => $verifiedAt,
                'id' => $tenantId,
            ]);
        } catch (\Throwable $e) {
            $msg = (string)$e->getMessage();
            if (stripos($msg, 'uniq_tenants_public_domain') !== false) {
                Session::flash('error', 'Ce domaine est déjà utilisé par un autre site.');
                redirect('/admin/public-site/domain');
            }
            Session::flash('error', 'Impossible d\'enregistrer le domaine (migration public_site manquante ? Lance /admin/update).');
            redirect('/admin/public-site/domain');
        }

        if ($domain === '') {
            Session::flash('success', 'Domaine supprimé.');
        } else {
            Session::flash('success', 'Domaine enregistré. Ajoute maintenant le TXT de vérification puis clique sur Vérifier.');
        }
        redirect('/admin/public-site/domain');
    }

    public static function verify(): void
    {
        self::requireAdmin();

        $tenantId = (int)$_SESSION['tenant_id'];
        self::requireModuleEnabled($tenantId);

        try {
            $pdo = Db::pdo();
            $stmt = $pdo->prepare('SELECT public_domain, public_domain_verification_token FROM tenants WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $tenantId]);
            $row = $stmt->fetch();
        } catch (\Throwable $e) {
            Session::flash('error', 'Impossible de vérifier le domaine (migration public_site manquante ? Lance /admin/update).');
            redirect('/admin/public-site/domain');
        }
        if (!$row) {
            http_response_code(404);
            echo '404';
            return;
        }

        $domain = trim((string)($row['public_domain'] ?? ''));
        $token = trim((string)($row['public_domain_verification_token'] ?? ''));

        if ($domain === '' || $token === '') {
            Session::flash('error', 'Domaine ou token manquant.');
            redirect('/admin/public-site/domain');
        }

        $expected = 'assofacile-verify=' . $token;
        $ok = false;

        try {
            $records = dns_get_record($domain, DNS_TXT);
            if (is_array($records)) {
                foreach ($records as $r) {
                    $txt = (string)($r['txt'] ?? '');
                    $txt = trim($txt);
                    if ($txt === $expected || str_contains($txt, $expected)) {
                        $ok = true;
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {
            $ok = false;
        }

        if (!$ok) {
            Session::flash('error', 'Vérification échouée. Assure-toi que le TXT est présent et attends la propagation DNS.');
            redirect('/admin/public-site/domain');
        }

        try {
            $stmt = $pdo->prepare('UPDATE tenants SET public_domain_status = :status, public_domain_verified_at = :verified_at WHERE id = :id LIMIT 1');
            $stmt->execute([
                'status' => 'verified',
                'verified_at' => date('Y-m-d H:i:s'),
                'id' => $tenantId,
            ]);
        } catch (\Throwable $e) {
            Session::flash('error', 'Impossible de finaliser la vérification.');
            redirect('/admin/public-site/domain');
        }

        Session::flash('success', 'Domaine vérifié.');
        redirect('/admin/public-site/domain');
    }
}
