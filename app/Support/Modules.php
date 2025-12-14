<?php

declare(strict_types=1);

namespace App\Support;

use App\Database\Db;

final class Modules
{
    public static function isEnabled(int $tenantId, string $moduleKey): bool
    {
        if ($tenantId <= 0 || $moduleKey === '') {
            return false;
        }

        if (!License::isModuleFree($moduleKey)) {
            if (License::shouldRecheck($tenantId)) {
                License::validateOnline($tenantId, $_SERVER['HTTP_HOST'] ?? null, null);
            }
        }

        if (isset($_SESSION['_modules_enabled'][$tenantId][$moduleKey])) {
            return (bool)$_SESSION['_modules_enabled'][$tenantId][$moduleKey];
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare(
            'SELECT tm.is_enabled
             FROM tenant_modules tm
             INNER JOIN modules m ON m.id = tm.module_id
             WHERE tm.tenant_id = :tenant_id AND m.module_key = :module_key
             LIMIT 1'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'module_key' => $moduleKey,
        ]);
        $row = $stmt->fetch();

        $enabled = $row ? ((int)$row['is_enabled'] === 1) : false;

        if ($enabled && !License::isModuleFree($moduleKey)) {
            $enabled = License::isPaidFeatureAllowed($tenantId);
        }
        $_SESSION['_modules_enabled'][$tenantId][$moduleKey] = $enabled;

        return $enabled;
    }

    public static function clearCacheForTenant(int $tenantId): void
    {
        unset($_SESSION['_modules_enabled'][$tenantId]);
    }
}
