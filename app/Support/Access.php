<?php

declare(strict_types=1);

namespace App\Support;

use App\Database\Db;

final class Access
{
    public static function can(int $tenantId, int $userId, string $moduleKey, string $action = 'read'): bool
    {
        if ($tenantId <= 0 || $userId <= 0 || $moduleKey === '') {
            return false;
        }

        if (!Modules::isEnabled($tenantId, $moduleKey)) {
            return false;
        }

        if (isset($_SESSION['is_admin']) && (int)$_SESSION['is_admin'] === 1) {
            return true;
        }

        if (!self::tenantHasPermissionsConfigured($tenantId)) {
            return true;
        }

        if (!in_array($action, ['read', 'write'], true)) {
            $action = 'read';
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare(
            'SELECT ump.can_read, ump.can_write
             FROM user_module_permissions ump
             INNER JOIN modules m ON m.id = ump.module_id
             WHERE ump.tenant_id = :tenant_id AND ump.user_id = :user_id AND m.module_key = :module_key
             LIMIT 1'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'module_key' => $moduleKey,
        ]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }

        $canRead = ((int)$row['can_read'] === 1);
        $canWrite = ((int)$row['can_write'] === 1);

        if ($action === 'write') {
            return $canWrite;
        }

        return $canRead || $canWrite;
    }

    private static function tenantHasPermissionsConfigured(int $tenantId): bool
    {
        if ($tenantId <= 0) {
            return false;
        }

        if (isset($_SESSION['_tenant_has_permissions'][$tenantId])) {
            return (bool)$_SESSION['_tenant_has_permissions'][$tenantId];
        }

        $pdo = Db::pdo();
        try {
            $stmt = $pdo->prepare('SELECT 1 FROM user_module_permissions WHERE tenant_id = :tenant_id LIMIT 1');
            $stmt->execute(['tenant_id' => $tenantId]);
            $has = (bool)$stmt->fetch();
        } catch (\Throwable $e) {
            $has = false;
        }

        $_SESSION['_tenant_has_permissions'][$tenantId] = $has;
        return $has;
    }

    public static function require(string $moduleKey, string $action = 'read'): void
    {
        if (!isset($_SESSION['user_id'], $_SESSION['tenant_id'])) {
            redirect('/login');
        }

        $tenantId = (int)$_SESSION['tenant_id'];
        $userId = (int)$_SESSION['user_id'];

        if (!self::can($tenantId, $userId, $moduleKey, $action)) {
            http_response_code(403);
            echo '403';
            exit;
        }
    }
}
