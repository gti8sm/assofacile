<?php

declare(strict_types=1);

namespace App\Support;

use App\Database\Db;

final class License
{
    public const FREE_MODULES = [
        'treasury',
    ];

    public static function graceDays(): int
    {
        $raw = Env::get('LICENSE_GRACE_DAYS', '30');
        $days = (int)$raw;
        return $days > 0 ? $days : 30;
    }

    public static function isModuleFree(string $moduleKey): bool
    {
        return in_array($moduleKey, self::FREE_MODULES, true);
    }

    public static function isPaidFeatureAllowed(int $tenantId): bool
    {
        if ($tenantId <= 0) {
            return false;
        }

        $row = self::getLicenseRow($tenantId);
        if (!$row) {
            return false;
        }

        $status = (string)($row['status'] ?? 'unknown');
        if ($status === 'active') {
            return true;
        }

        $graceUntil = (string)($row['grace_until'] ?? '');
        if ($graceUntil !== '') {
            return self::isDateInFuture($graceUntil);
        }

        return false;
    }

    public static function upsertKey(int $tenantId, string $licenseKey): void
    {
        $pdo = Db::pdo();
        $stmt = $pdo->prepare(
            "INSERT INTO tenant_licenses (tenant_id, license_key, status)
             VALUES (:tenant_id, :license_key, 'unknown')
             ON DUPLICATE KEY UPDATE license_key = VALUES(license_key), status = 'unknown', last_error = NULL"
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'license_key' => $licenseKey,
        ]);
    }

    /**
     * @return array{ok: bool, status: string, message: ?string}
     */
    public static function validateOnline(int $tenantId, ?string $appUrl = null, ?string $appVersion = null): array
    {
        $row = self::getLicenseRow($tenantId);
        if (!$row) {
            return ['ok' => false, 'status' => 'missing', 'message' => 'Aucune licence configurée.'];
        }

        $licenseKey = (string)($row['license_key'] ?? '');
        if ($licenseKey === '') {
            return ['ok' => false, 'status' => 'missing', 'message' => 'Aucune licence configurée.'];
        }

        $server = Env::get('LICENSE_SERVER_URL', 'https://licences.assofacile.fr');
        $server = rtrim((string)$server, '/');
        $url = $server . '/api/v1/licenses/validate';

        $payload = [
            'license_key' => $licenseKey,
            'tenant_id' => $tenantId,
            'app_url' => $appUrl,
            'app_version' => $appVersion,
        ];

        $json = json_encode($payload);
        if ($json === false) {
            return ['ok' => false, 'status' => 'error', 'message' => 'Erreur JSON.'];
        }

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => $json,
                'timeout' => 8,
            ],
        ]);

        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            self::touchCheckError($tenantId, 'Serveur de licence injoignable.');
            return ['ok' => false, 'status' => 'unreachable', 'message' => 'Serveur de licence injoignable.'];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            self::touchCheckError($tenantId, 'Réponse licence invalide.');
            return ['ok' => false, 'status' => 'error', 'message' => 'Réponse licence invalide.'];
        }

        $status = (string)($data['status'] ?? 'invalid');
        $planType = isset($data['plan_type']) ? (string)$data['plan_type'] : null;
        $validUntil = isset($data['valid_until']) ? (string)$data['valid_until'] : null;

        $graceDays = self::graceDays();
        $graceUntil = date('Y-m-d', time() + ($graceDays * 86400));

        $pdo = Db::pdo();
        $stmt = $pdo->prepare(
            'UPDATE tenant_licenses
             SET status = :status,
                 plan_type = :plan_type,
                 valid_until = :valid_until,
                 grace_until = :grace_until,
                 last_checked_at = NOW(),
                 last_error = NULL
             WHERE tenant_id = :tenant_id'
        );

        $stmt->execute([
            'tenant_id' => $tenantId,
            'status' => $status,
            'plan_type' => $planType,
            'valid_until' => $validUntil,
            'grace_until' => $status === 'active' ? null : $graceUntil,
        ]);

        return ['ok' => $status === 'active', 'status' => $status, 'message' => null];
    }

    public static function shouldRecheck(int $tenantId): bool
    {
        $row = self::getLicenseRow($tenantId);
        if (!$row) {
            return true;
        }

        $last = (string)($row['last_checked_at'] ?? '');
        if ($last === '') {
            return true;
        }

        $ts = strtotime($last);
        if ($ts === false) {
            return true;
        }

        return (time() - $ts) > 86400;
    }

    /** @return array<string, mixed>|null */
    public static function getLicenseRow(int $tenantId): ?array
    {
        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT * FROM tenant_licenses WHERE tenant_id = :tenant_id LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private static function touchCheckError(int $tenantId, string $message): void
    {
        $row = self::getLicenseRow($tenantId);
        if (!$row) {
            return;
        }

        $graceUntil = (string)($row['grace_until'] ?? '');
        if ($graceUntil === '') {
            $days = self::graceDays();
            $graceUntil = date('Y-m-d', time() + ($days * 86400));
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('UPDATE tenant_licenses SET last_checked_at = NOW(), last_error = :err, grace_until = :grace_until WHERE tenant_id = :tenant_id');
        $stmt->execute([
            'tenant_id' => $tenantId,
            'err' => $message,
            'grace_until' => $graceUntil,
        ]);
    }

    private static function isDateInFuture(string $ymd): bool
    {
        $ts = strtotime($ymd . ' 23:59:59');
        if ($ts === false) {
            return false;
        }
        return $ts >= time();
    }
}
