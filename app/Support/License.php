<?php

declare(strict_types=1);

namespace App\Support;

use App\Database\Db;

final class License
{
    public const FREE_MODULES = [
        // Core gratuit : pas de module payant ici.
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

    public static function isModuleAllowed(int $tenantId, string $moduleKey): bool
    {
        if ($tenantId <= 0 || $moduleKey === '') {
            return false;
        }

        if (self::isModuleFree($moduleKey)) {
            return true;
        }

        $row = self::getLicenseRow($tenantId);
        if (!$row) {
            return false;
        }

        $payload = self::getSignedTokenPayloadIfValid($row);
        if (is_array($payload)) {
            $status = (string)($payload['status'] ?? 'unknown');
            if ($status !== 'active') {
                return false;
            }

            $ent = $payload['entitlements'] ?? null;
            if (is_array($ent) && isset($ent[$moduleKey]) && is_array($ent[$moduleKey])) {
                $validUntil = (string)($ent[$moduleKey]['valid_until'] ?? '');
                if ($validUntil !== '') {
                    return self::isDateInFuture($validUntil);
                }
                return false;
            }

            // Compat legacy: token valide mais sans entitlements => ancien modèle "licence globale"
            return true;
        }

        // Compat legacy: si l'app n'a pas de token signé exploitable, on retombe sur l'ancien mécanisme.
        return self::isPaidFeatureAllowed($tenantId);
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

        if (self::isSignedTokenValid($row)) {
            return true;
        }

        $graceUntil = (string)($row['grace_until'] ?? '');
        if ($graceUntil !== '') {
            return self::isDateInFuture($graceUntil);
        }

        return false;
    }

    /** @param array<string,mixed> $row */
    private static function isSignedTokenValid(array $row): bool
    {
        $token = (string)($row['signed_token'] ?? '');
        $validUntil = (string)($row['token_valid_until'] ?? '');
        if ($token === '' || $validUntil === '') {
            return false;
        }

        if (!self::isDateInFuture($validUntil)) {
            return false;
        }

        $pub = (string)(Env::get('LICENSE_PUBLIC_KEY', '') ?? '');
        if ($pub === '') {
            return false;
        }

        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            return false;
        }

        return self::verifyEd25519Token($token, $pub);
    }

    /** @param array<string,mixed> $row @return array<string,mixed>|null */
    private static function getSignedTokenPayloadIfValid(array $row): ?array
    {
        if (!self::isSignedTokenValid($row)) {
            return null;
        }

        $token = (string)($row['signed_token'] ?? '');
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        $payloadJson = self::b64urlDecode($parts[1]);
        if ($payloadJson === false || $payloadJson === '') {
            return null;
        }

        $payload = json_decode($payloadJson, true);
        return is_array($payload) ? $payload : null;
    }

    private static function verifyEd25519Token(string $token, string $publicKeyB64): bool
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        [$h, $p, $s] = $parts;
        if ($h === '' || $p === '' || $s === '') {
            return false;
        }

        $msg = $h . '.' . $p;
        $sig = self::b64urlDecode($s);
        $pub = base64_decode($publicKeyB64, true);
        if ($sig === false || $pub === false) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($sig, $msg, $pub);
    }

    private static function b64urlDecode(string $in): string|false
    {
        $b64 = strtr($in, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad > 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }
        return base64_decode($b64, true);
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
        $signedToken = isset($data['signed_token']) ? (string)$data['signed_token'] : null;
        $tokenValidUntil = isset($data['token_valid_until']) ? (string)$data['token_valid_until'] : null;

        $graceDays = self::graceDays();
        $graceUntil = date('Y-m-d', time() + ($graceDays * 86400));

        $pdo = Db::pdo();
        $stmt = $pdo->prepare(
            'UPDATE tenant_licenses
             SET status = :status,
                 plan_type = :plan_type,
                 valid_until = :valid_until,
                 grace_until = :grace_until,
                 signed_token = :signed_token,
                 token_valid_until = :token_valid_until,
                 token_issued_at = IF(:signed_token IS NULL, token_issued_at, NOW()),
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
            'signed_token' => $signedToken,
            'token_valid_until' => $tokenValidUntil,
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
