<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Db;
use App\Support\Modules;
use App\Support\Session;

final class AdminModulesController
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

        $pdo = Db::pdo();

        $modules = $pdo->query('SELECT id, module_key, name FROM modules ORDER BY name ASC')->fetchAll();

        $stmt = $pdo->prepare(
            'SELECT m.module_key, tm.is_enabled
             FROM tenant_modules tm
             INNER JOIN modules m ON m.id = tm.module_id
             WHERE tm.tenant_id = :tenant_id'
        );
        $stmt->execute(['tenant_id' => (int)$_SESSION['tenant_id']]);
        $enabledRows = $stmt->fetchAll();

        $enabledByKey = [];
        foreach ($enabledRows as $row) {
            $enabledByKey[(string)$row['module_key']] = ((int)$row['is_enabled'] === 1);
        }

        $flash = Session::flash('success');
        require base_path('views/admin/modules.php');
    }

    public static function update(): void
    {
        self::requireAdmin();

        $tenantId = (int)$_SESSION['tenant_id'];
        $posted = $_POST['modules'] ?? [];
        $postedKeys = is_array($posted) ? array_keys($posted) : [];

        $pdo = Db::pdo();
        $modules = $pdo->query('SELECT id, module_key FROM modules')->fetchAll();

        $pdo->beginTransaction();
        try {
            foreach ($modules as $m) {
                $moduleId = (int)$m['id'];
                $key = (string)$m['module_key'];
                $enabled = in_array($key, $postedKeys, true) ? 1 : 0;

                $stmt = $pdo->prepare(
                    'INSERT INTO tenant_modules (tenant_id, module_id, is_enabled, enabled_at)
                     VALUES (:tenant_id, :module_id, :is_enabled, IF(:is_enabled = 1, CURRENT_TIMESTAMP, NULL))
                     ON DUPLICATE KEY UPDATE
                       is_enabled = VALUES(is_enabled),
                       enabled_at = IF(VALUES(is_enabled) = 1, COALESCE(enabled_at, CURRENT_TIMESTAMP), NULL)'
                );
                $stmt->execute([
                    'tenant_id' => $tenantId,
                    'module_id' => $moduleId,
                    'is_enabled' => $enabled,
                ]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        Modules::clearCacheForTenant($tenantId);
        Session::flash('success', 'Modules mis à jour.');
        redirect('/admin/modules');
    }
}
