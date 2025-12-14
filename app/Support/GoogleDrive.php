<?php

declare(strict_types=1);

namespace App\Support;

use App\Database\Db;

final class GoogleDrive
{
    public static function isConfigured(): bool
    {
        return (Env::get('GOOGLE_CLIENT_ID') ?? '') !== ''
            && (Env::get('GOOGLE_CLIENT_SECRET') ?? '') !== ''
            && (Env::get('GOOGLE_REDIRECT_URI') ?? '') !== '';
    }

    public static function isConnected(int $tenantId): bool
    {
        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT drive_refresh_token FROM tenant_google_tokens WHERE tenant_id = :tenant_id LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId]);
        $row = $stmt->fetch();
        return $row && !empty($row['drive_refresh_token']);
    }

    public static function isAvailable(): bool
    {
        return DriveStorage::isAvailable();
    }

    public static function getClient(int $tenantId): ?\Google_Client
    {
        if (!self::isConfigured() || !self::isAvailable()) {
            return null;
        }

        $client = new \Google_Client();
        $client->setClientId((string)Env::get('GOOGLE_CLIENT_ID'));
        $client->setClientSecret((string)Env::get('GOOGLE_CLIENT_SECRET'));
        $client->setRedirectUri((string)Env::get('GOOGLE_REDIRECT_URI'));
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setScopes([
            \Google_Service_Drive::DRIVE_FILE,
        ]);

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT drive_access_token, drive_refresh_token, drive_token_expires_at FROM tenant_google_tokens WHERE tenant_id = :tenant_id LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId]);
        $row = $stmt->fetch();
        if (!$row || empty($row['drive_refresh_token'])) {
            return $client;
        }

        $expiresAt = (int)($row['drive_token_expires_at'] ?? 0);
        $expiresIn = $expiresAt > time() ? ($expiresAt - time()) : 0;

        $token = [
            'access_token' => (string)($row['drive_access_token'] ?? ''),
            'refresh_token' => (string)$row['drive_refresh_token'],
            'expires_in' => $expiresIn,
            'created' => time(),
        ];
        $client->setAccessToken($token);

        if ($client->isAccessTokenExpired()) {
            $newToken = $client->fetchAccessTokenWithRefreshToken((string)$row['drive_refresh_token']);
            if (is_array($newToken) && isset($newToken['access_token'])) {
                self::storeToken($tenantId, $newToken, (string)$row['drive_refresh_token']);
                $client->setAccessToken($newToken);
            }
        }

        return $client;
    }

    public static function getService(int $tenantId): ?\Google_Service_Drive
    {
        $client = self::getClient($tenantId);
        if (!$client) {
            return null;
        }

        return new \Google_Service_Drive($client);
    }

    public static function getDriveFolderId(int $tenantId): ?string
    {
        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT drive_folder_id FROM tenant_google_tokens WHERE tenant_id = :tenant_id LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId]);
        $row = $stmt->fetch();
        $id = (string)($row['drive_folder_id'] ?? '');
        return $id !== '' ? $id : null;
    }

    public static function getAuthUrl(int $tenantId): ?string
    {
        $client = self::getClient($tenantId);
        if (!$client) {
            return null;
        }
        return $client->createAuthUrl();
    }

    public static function exchangeCode(int $tenantId, string $code): bool
    {
        $client = self::getClient($tenantId);
        if (!$client) {
            return false;
        }

        $token = $client->fetchAccessTokenWithAuthCode($code);
        if (!is_array($token) || isset($token['error'])) {
            return false;
        }

        $refreshToken = (string)($token['refresh_token'] ?? '');
        if ($refreshToken === '') {
            $pdo = Db::pdo();
            $stmt = $pdo->prepare('SELECT drive_refresh_token FROM tenant_google_tokens WHERE tenant_id = :tenant_id LIMIT 1');
            $stmt->execute(['tenant_id' => $tenantId]);
            $row = $stmt->fetch();
            $refreshToken = (string)($row['drive_refresh_token'] ?? '');
        }

        if ($refreshToken === '') {
            return false;
        }

        self::storeToken($tenantId, $token, $refreshToken);
        return true;
    }

    public static function disconnect(int $tenantId): void
    {
        $pdo = Db::pdo();
        $stmt = $pdo->prepare('DELETE FROM tenant_google_tokens WHERE tenant_id = :tenant_id');
        $stmt->execute(['tenant_id' => $tenantId]);
    }

    private static function storeToken(int $tenantId, array $token, string $refreshToken): void
    {
        $accessToken = (string)($token['access_token'] ?? '');
        $expiresIn = (int)($token['expires_in'] ?? 3600);
        $expiresAt = time() + max(60, $expiresIn);

        $pdo = Db::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO tenant_google_tokens (tenant_id, drive_access_token, drive_refresh_token, drive_token_expires_at)
             VALUES (:tenant_id, :access_token, :refresh_token, :expires_at)
             ON DUPLICATE KEY UPDATE
               drive_access_token = VALUES(drive_access_token),
               drive_refresh_token = VALUES(drive_refresh_token),
               drive_token_expires_at = VALUES(drive_token_expires_at)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at' => $expiresAt,
        ]);
    }
}
