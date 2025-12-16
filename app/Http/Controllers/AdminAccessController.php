<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Db;
use App\Support\Modules;
use App\Support\Session;

final class AdminAccessController
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
        $pdo = Db::pdo();

        $users = $pdo->prepare('SELECT id, email, full_name, role, is_active, is_admin FROM users WHERE tenant_id = :tenant_id ORDER BY is_admin DESC, is_active DESC, email ASC');
        $users->execute(['tenant_id' => $tenantId]);
        $users = $users->fetchAll();

        $modules = $pdo->query('SELECT id, module_key, name FROM modules ORDER BY name ASC')->fetchAll();

        $stmt = $pdo->prepare(
            'SELECT m.module_key, ump.user_id, ump.can_read, ump.can_write
             FROM user_module_permissions ump
             INNER JOIN modules m ON m.id = ump.module_id
             WHERE ump.tenant_id = :tenant_id'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        $rows = $stmt->fetchAll();

        $perm = [];
        foreach ($rows as $r) {
            $u = (int)$r['user_id'];
            $k = (string)$r['module_key'];
            $perm[$u][$k] = [
                'read' => ((int)$r['can_read'] === 1),
                'write' => ((int)$r['can_write'] === 1),
            ];
        }

        $enabledByKey = [];
        foreach ($modules as $m) {
            $key = (string)$m['module_key'];
            $enabledByKey[$key] = Modules::isEnabled($tenantId, $key);
        }

        $flash = Session::flash('success');
        $error = Session::flash('error');

        require base_path('views/admin/access.php');
    }

    public static function update(): void
    {
        self::requireAdmin();

        $tenantId = (int)$_SESSION['tenant_id'];
        $posted = $_POST['perm'] ?? [];
        if (!is_array($posted)) {
            $posted = [];
        }

        $pdo = Db::pdo();
        $modules = $pdo->query('SELECT id, module_key FROM modules')->fetchAll();
        $moduleIdByKey = [];
        foreach ($modules as $m) {
            $moduleIdByKey[(string)$m['module_key']] = (int)$m['id'];
        }

        $pdo->beginTransaction();
        try {
            foreach ($posted as $userIdStr => $byModule) {
                $userId = (int)$userIdStr;
                if ($userId <= 0 || !is_array($byModule)) {
                    continue;
                }

                foreach ($byModule as $moduleKey => $actions) {
                    $moduleKey = (string)$moduleKey;
                    if ($moduleKey === '' || !isset($moduleIdByKey[$moduleKey]) || !is_array($actions)) {
                        continue;
                    }

                    $canRead = isset($actions['read']) ? 1 : 0;
                    $canWrite = isset($actions['write']) ? 1 : 0;

                    $stmt = $pdo->prepare(
                        'INSERT INTO user_module_permissions (tenant_id, user_id, module_id, can_read, can_write)
                         VALUES (:tenant_id, :user_id, :module_id, :can_read, :can_write)
                         ON DUPLICATE KEY UPDATE
                           can_read = VALUES(can_read),
                           can_write = VALUES(can_write)'
                    );
                    $stmt->execute([
                        'tenant_id' => $tenantId,
                        'user_id' => $userId,
                        'module_id' => (int)$moduleIdByKey[$moduleKey],
                        'can_read' => $canRead,
                        'can_write' => $canWrite,
                    ]);
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        unset($_SESSION['_tenant_has_permissions'][$tenantId]);
        Session::flash('success', 'Accès mis à jour.');
        redirect('/admin/access');
    }
}
