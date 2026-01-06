<?php

declare(strict_types=1);

namespace App\Support;

use App\Database\Db;

final class GoogleDrive
{
    /** @var array<string, string> */
    private static array $folderCache = [];

    private static function findFolderId(int $tenantId, ?string $parentId, string $name): ?string
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $cacheKey = self::folderCacheKey($tenantId, $parentId, $name);
        if (isset(self::$folderCache[$cacheKey])) {
            return self::$folderCache[$cacheKey];
        }

        $drive = self::getService($tenantId);
        if ($drive === null) {
            return null;
        }

        $escapedName = str_replace("'", "\\'", $name);
        $q = "mimeType='application/vnd.google-apps.folder' and name='" . $escapedName . "' and trashed=false";
        if ($parentId !== null && trim($parentId) !== '') {
            $q .= " and '" . str_replace("'", "\\'", $parentId) . "' in parents";
        }

        try {
            $list = $drive->files->listFiles([
                'q' => $q,
                'fields' => 'files(id,name)',
                'pageSize' => 1,
            ]);
            $files = $list ? $list->getFiles() : null;
            if (is_array($files) && isset($files[0])) {
                $id = (string)($files[0]->getId() ?? '');
                if ($id !== '') {
                    self::$folderCache[$cacheKey] = $id;
                    return $id;
                }
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

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
            \Google_Service_Drive::DRIVE_METADATA_READONLY,
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

    public static function setDriveFolderId(int $tenantId, ?string $folderId): void
    {
        $folderId = $folderId !== null ? trim($folderId) : null;
        if ($folderId === '') {
            $folderId = null;
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('UPDATE tenant_google_tokens SET drive_folder_id = :folder_id WHERE tenant_id = :tenant_id');
        $stmt->execute([
            'tenant_id' => $tenantId,
            'folder_id' => $folderId,
        ]);
    }

    private static function folderCacheKey(int $tenantId, ?string $parentId, string $name): string
    {
        return $tenantId . '|' . (string)($parentId ?? '') . '|' . $name;
    }

    private static function getOrCreateFolder(int $tenantId, ?string $parentId, string $name): ?string
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $cacheKey = self::folderCacheKey($tenantId, $parentId, $name);
        if (isset(self::$folderCache[$cacheKey])) {
            return self::$folderCache[$cacheKey];
        }

        $drive = self::getService($tenantId);
        if ($drive === null) {
            return null;
        }

        $escapedName = str_replace("'", "\\'", $name);
        $q = "mimeType='application/vnd.google-apps.folder' and name='" . $escapedName . "' and trashed=false";
        if ($parentId !== null && trim($parentId) !== '') {
            $q .= " and '" . str_replace("'", "\\'", $parentId) . "' in parents";
        }

        try {
            $list = $drive->files->listFiles([
                'q' => $q,
                'fields' => 'files(id,name)',
                'pageSize' => 1,
            ]);
            $files = $list ? $list->getFiles() : null;
            if (is_array($files) && isset($files[0])) {
                $id = (string)($files[0]->getId() ?? '');
                if ($id !== '') {
                    self::$folderCache[$cacheKey] = $id;
                    return $id;
                }
            }

            $meta = [
                'name' => $name,
                'mimeType' => 'application/vnd.google-apps.folder',
            ];
            if ($parentId !== null && trim($parentId) !== '') {
                $meta['parents'] = [$parentId];
            }
            $fileMetadata = new \Google_Service_Drive_DriveFile($meta);
            $created = $drive->files->create($fileMetadata, ['fields' => 'id']);
            $id = (string)($created->id ?? '');
            if ($id !== '') {
                self::$folderCache[$cacheKey] = $id;
                return $id;
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    public static function ensureTreasuryAttachmentFolder(int $tenantId, ?string $rootFolderId, string $year, string $month, int $transactionId): ?string
    {
        $year = trim($year);
        $month = trim($month);
        if (!preg_match('/^\d{4}$/', $year)) {
            $year = date('Y');
        }
        if (!preg_match('/^\d{2}$/', $month)) {
            $month = date('m');
        }
        if ($transactionId <= 0) {
            return null;
        }

        $lvl1 = self::getOrCreateFolder($tenantId, $rootFolderId, 'Trésorerie');
        if ($lvl1 === null) {
            return null;
        }
        $lvl2 = self::getOrCreateFolder($tenantId, $lvl1, $year);
        if ($lvl2 === null) {
            return null;
        }
        $lvl3 = self::getOrCreateFolder($tenantId, $lvl2, $month);
        if ($lvl3 === null) {
            return null;
        }

        return self::getOrCreateFolder($tenantId, $lvl3, 'tx_' . $transactionId);
    }

    public static function ensureProjectDocumentsFolder(int $tenantId, ?string $rootFolderId, int $projectId): ?string
    {
        if ($projectId <= 0) {
            return null;
        }

        $lvl1 = self::getOrCreateFolder($tenantId, $rootFolderId, 'Projets');
        if ($lvl1 === null) {
            return null;
        }

        return self::getOrCreateFolder($tenantId, $lvl1, 'project_' . $projectId);
    }

    public static function ensureProjectDocumentsTrashFolder(int $tenantId, ?string $rootFolderId, int $projectId): ?string
    {
        if ($projectId <= 0) {
            return null;
        }

        $lvl1 = self::getOrCreateFolder($tenantId, $rootFolderId, 'Projets');
        if ($lvl1 === null) {
            return null;
        }

        $trash = self::getOrCreateFolder($tenantId, $lvl1, 'Corbeille');
        if ($trash === null) {
            return null;
        }

        return self::getOrCreateFolder($tenantId, $trash, 'project_' . $projectId);
    }

    public static function ensureMemberDocumentsFolder(int $tenantId, ?string $rootFolderId, int $memberId, ?string $memberSlug = null): ?string
    {
        if ($memberId <= 0) {
            return null;
        }

        $lvl1 = self::getOrCreateFolder($tenantId, $rootFolderId, 'Adhérents');
        if ($lvl1 === null) {
            return null;
        }

        $legacyName = 'member_' . $memberId;
        if ($memberSlug !== null) {
            $memberSlug = trim($memberSlug);
        }

        if ($memberSlug !== null && $memberSlug !== '') {
            $existingLegacyId = self::findFolderId($tenantId, $lvl1, $legacyName);
            if ($existingLegacyId !== null) {
                return $existingLegacyId;
            }

            return self::getOrCreateFolder($tenantId, $lvl1, $legacyName . '_' . $memberSlug);
        }

        return self::getOrCreateFolder($tenantId, $lvl1, $legacyName);
    }

    public static function ensureMemberDocumentsTrashFolder(int $tenantId, ?string $rootFolderId, int $memberId, ?string $memberSlug = null): ?string
    {
        if ($memberId <= 0) {
            return null;
        }

        $lvl1 = self::getOrCreateFolder($tenantId, $rootFolderId, 'Adhérents');
        if ($lvl1 === null) {
            return null;
        }

        $trash = self::getOrCreateFolder($tenantId, $lvl1, 'Corbeille');
        if ($trash === null) {
            return null;
        }

        $legacyName = 'member_' . $memberId;
        if ($memberSlug !== null) {
            $memberSlug = trim($memberSlug);
        }

        if ($memberSlug !== null && $memberSlug !== '') {
            $existingLegacyId = self::findFolderId($tenantId, $trash, $legacyName);
            if ($existingLegacyId !== null) {
                return $existingLegacyId;
            }

            return self::getOrCreateFolder($tenantId, $trash, $legacyName . '_' . $memberSlug);
        }

        return self::getOrCreateFolder($tenantId, $trash, $legacyName);
    }

    public static function ensureTierDocumentsFolder(int $tenantId, ?string $rootFolderId, int $tierId): ?string
    {
        if ($tierId <= 0) {
            return null;
        }

        $lvl1 = self::getOrCreateFolder($tenantId, $rootFolderId, 'Tiers');
        if ($lvl1 === null) {
            return null;
        }

        return self::getOrCreateFolder($tenantId, $lvl1, 'tier_' . $tierId);
    }

    public static function ensureTierDocumentsTrashFolder(int $tenantId, ?string $rootFolderId, int $tierId): ?string
    {
        if ($tierId <= 0) {
            return null;
        }

        $lvl1 = self::getOrCreateFolder($tenantId, $rootFolderId, 'Tiers');
        if ($lvl1 === null) {
            return null;
        }

        $trash = self::getOrCreateFolder($tenantId, $lvl1, 'Corbeille');
        if ($trash === null) {
            return null;
        }

        return self::getOrCreateFolder($tenantId, $trash, 'tier_' . $tierId);
    }

    public static function getFolderMeta(int $tenantId, string $folderId): ?array
    {
        $folderId = trim($folderId);
        if ($folderId === '') {
            return null;
        }

        $drive = self::getService($tenantId);
        if ($drive === null) {
            return null;
        }

        try {
            $file = $drive->files->get($folderId, ['fields' => 'id,name,mimeType']);
        } catch (\Google\Service\Exception $e) {
            $msg = 'Erreur Google Drive.';
            $details = $e->getErrors();
            if (is_array($details) && isset($details[0]) && is_array($details[0])) {
                $reason = isset($details[0]['reason']) ? (string)$details[0]['reason'] : '';
                $emsg = isset($details[0]['message']) ? (string)$details[0]['message'] : '';
                $parts = array_values(array_filter([$reason, $emsg], static fn ($v) => $v !== ''));
                if (!empty($parts)) {
                    $msg = implode(': ', $parts);
                }
            }
            throw new \RuntimeException($msg, (int)$e->getCode(), $e);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Erreur Google Drive.', (int)$e->getCode(), $e);
        }
        if (!$file) {
            return null;
        }

        $mimeType = (string)($file->getMimeType() ?? '');
        return [
            'id' => (string)($file->getId() ?? ''),
            'name' => (string)($file->getName() ?? ''),
            'mimeType' => $mimeType,
            'isFolder' => ($mimeType === 'application/vnd.google-apps.folder'),
        ];
    }

    public static function getAuthUrl(int $tenantId, ?string $state = null): ?string
    {
        $client = self::getClient($tenantId);
        if (!$client) {
            return null;
        }

        if ($state !== null) {
            $state = trim($state);
            if ($state !== '') {
                $client->setState($state);
            }
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
