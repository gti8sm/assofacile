<?php

declare(strict_types=1);

namespace App\Support;

use App\Database\Db;

final class ModuleSettings
{
    public static function getRaw(int $tenantId, string $moduleKey, string $settingKey): ?string
    {
        if ($tenantId <= 0 || $moduleKey === '' || $settingKey === '') {
            return null;
        }

        if (isset($_SESSION['_module_settings'][$tenantId][$moduleKey][$settingKey])) {
            return $_SESSION['_module_settings'][$tenantId][$moduleKey][$settingKey];
        }

        try {
            $pdo = Db::pdo();
            $stmt = $pdo->prepare(
                'SELECT value_json
                 FROM tenant_module_settings
                 WHERE tenant_id = :tenant_id AND module_key = :module_key AND setting_key = :setting_key
                 LIMIT 1'
            );
            $stmt->execute([
                'tenant_id' => $tenantId,
                'module_key' => $moduleKey,
                'setting_key' => $settingKey,
            ]);
            $row = $stmt->fetch();
            $val = $row ? (string)($row['value_json'] ?? '') : null;
            $_SESSION['_module_settings'][$tenantId][$moduleKey][$settingKey] = $val;
        } catch (\Throwable $e) {
            $_SESSION['_module_settings'][$tenantId][$moduleKey][$settingKey] = null;
            return null;
        }

        return $val;
    }

    public static function getString(int $tenantId, string $moduleKey, string $settingKey, string $default): string
    {
        $raw = self::getRaw($tenantId, $moduleKey, $settingKey);
        if ($raw === null || $raw === '') {
            return $default;
        }

        $decoded = json_decode($raw, true);
        if (is_string($decoded)) {
            return $decoded;
        }

        return $default;
    }

    public static function getBool(int $tenantId, string $moduleKey, string $settingKey, bool $default): bool
    {
        $raw = self::getRaw($tenantId, $moduleKey, $settingKey);
        if ($raw === null || $raw === '') {
            return $default;
        }

        $decoded = json_decode($raw, true);
        if (is_bool($decoded)) {
            return $decoded;
        }
        if (is_int($decoded)) {
            return $decoded === 1;
        }
        if (is_string($decoded)) {
            return in_array(strtolower($decoded), ['1', 'true', 'yes', 'on'], true);
        }

        return $default;
    }

    public static function setBool(int $tenantId, string $moduleKey, string $settingKey, bool $value): void
    {
        self::setRaw($tenantId, $moduleKey, $settingKey, json_encode($value));
    }

    public static function setRaw(int $tenantId, string $moduleKey, string $settingKey, string $valueJson): void
    {
        $pdo = Db::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO tenant_module_settings (tenant_id, module_key, setting_key, value_json)
             VALUES (:tenant_id, :module_key, :setting_key, :value_json)
             ON DUPLICATE KEY UPDATE value_json = VALUES(value_json)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'module_key' => $moduleKey,
            'setting_key' => $settingKey,
            'value_json' => $valueJson,
        ]);

        unset($_SESSION['_module_settings'][$tenantId][$moduleKey][$settingKey]);
    }

    public static function clearCacheForTenant(int $tenantId): void
    {
        unset($_SESSION['_module_settings'][$tenantId]);
    }
}
