<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Db;
use App\Support\Access;
use App\Support\GoogleDrive;
use App\Support\Modules;
use App\Support\Session;
use App\Support\Storage;

final class TiersController
{
    private const MAX_DOC_FILE_SIZE = 10485760; // 10 MB

    private static function normalizeTierName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;
        $name = mb_strtolower($name);
        $name = iconv('UTF-8', 'ASCII//TRANSLIT', $name) ?: $name;
        $name = preg_replace('/[^a-z0-9]+/i', ' ', $name) ?? $name;
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);
        if ($name === '') {
            $name = 'tier';
        }
        if (mb_strlen($name) > 190) {
            $name = mb_substr($name, 0, 190);
        }
        return $name;
    }

    private static function upsertMemberTier(\PDO $pdo, int $tenantId, int $memberId, string $displayName): void
    {
        $displayName = trim(preg_replace('/\s+/', ' ', $displayName) ?? $displayName);
        if ($displayName === '') {
            $displayName = 'Adhérent #' . $memberId;
        }
        if (mb_strlen($displayName) > 190) {
            $displayName = mb_substr($displayName, 0, 190);
        }

        $norm = self::normalizeTierName($displayName);

        $stmt = $pdo->prepare(
            'INSERT INTO tiers (tenant_id, member_id, name, normalized_name)
             VALUES (:tenant_id, :member_id, :name, :normalized_name)
             ON DUPLICATE KEY UPDATE name = VALUES(name), normalized_name = VALUES(normalized_name)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'member_id' => $memberId,
            'name' => $displayName,
            'normalized_name' => $norm,
        ]);

        $stmt = $pdo->prepare('SELECT id FROM tiers WHERE tenant_id = :tenant_id AND member_id = :member_id LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId, 'member_id' => $memberId]);
        $tier = $stmt->fetch();
        $tierId = (int)($tier['id'] ?? 0);
        if ($tierId > 0) {
            $stmt = $pdo->prepare('INSERT IGNORE INTO tier_tags (tier_id, tag) VALUES (:tier_id, :tag)');
            $stmt->execute(['tier_id' => $tierId, 'tag' => 'adherent']);
        }
    }

    public static function index(): void
    {
        Access::require('treasury', 'read');

        $tenantId = (int)$_SESSION['tenant_id'];
        $q = trim((string)($_GET['q'] ?? ''));
        $tag = trim((string)($_GET['tag'] ?? ''));
        if (!in_array($tag, ['', 'adherent', 'partenaire', 'fournisseur'], true)) {
            $tag = '';
        }

        $pdo = Db::pdo();

        $sql = 'SELECT t.id, t.name, t.member_id,
                       GROUP_CONCAT(tt.tag ORDER BY tt.tag SEPARATOR ",") AS tags
                FROM tiers t
                LEFT JOIN tier_tags tt ON tt.tier_id = t.id
                WHERE t.tenant_id = :tenant_id';
        $params = ['tenant_id' => $tenantId];

        if ($q !== '') {
            $sql .= ' AND (t.name LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        if ($tag !== '') {
            $sql .= ' AND EXISTS (SELECT 1 FROM tier_tags tt2 WHERE tt2.tier_id = t.id AND tt2.tag = :tag)';
            $params['tag'] = $tag;
        }

        $sql .= ' GROUP BY t.id, t.name, t.member_id
                  ORDER BY t.name ASC
                  LIMIT 500';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $tiers = $stmt->fetchAll();

        $flash = Session::flash('success');
        $error = Session::flash('error');

        require base_path('views/tiers/index.php');
    }

    public static function view(): void
    {
        Access::require('treasury', 'read');

        $tenantId = (int)$_SESSION['tenant_id'];
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo '400';
            return;
        }

        $pdo = Db::pdo();

        $stmt = $pdo->prepare('SELECT id, name, member_id FROM tiers WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $tier = $stmt->fetch();
        if (!$tier) {
            http_response_code(404);
            echo '404';
            return;
        }

        $stmt = $pdo->prepare('SELECT tag FROM tier_tags WHERE tier_id = :tier_id ORDER BY tag ASC');
        $stmt->execute(['tier_id' => (int)$tier['id']]);
        $tags = array_map(static fn ($r) => (string)($r['tag'] ?? ''), $stmt->fetchAll());
        $tags = array_values(array_filter($tags, static fn ($t) => $t !== ''));

        $member = null;
        $memberId = (int)($tier['member_id'] ?? 0);
        if ($memberId > 0) {
            $stmt = $pdo->prepare('SELECT id, first_name, last_name, email, phone, status FROM members WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
            $stmt->execute(['id' => $memberId, 'tenant_id' => $tenantId]);
            $member = $stmt->fetch();
        }

        $stmt = $pdo->prepare(
            'SELECT
                SUM(CASE WHEN type = "income" THEN amount_cents ELSE 0 END) AS total_income_cents,
                SUM(CASE WHEN type = "expense" THEN amount_cents ELSE 0 END) AS total_expense_cents,
                SUM(CASE WHEN type = "income" THEN amount_cents ELSE -amount_cents END) AS net_cents,
                SUM(CASE WHEN YEAR(occurred_on) = YEAR(CURDATE()) AND type = "income" THEN amount_cents ELSE 0 END) AS ytd_income_cents,
                SUM(CASE WHEN YEAR(occurred_on) = YEAR(CURDATE()) AND type = "expense" THEN amount_cents ELSE 0 END) AS ytd_expense_cents,
                COUNT(*) AS tx_count
             FROM treasury_transactions
             WHERE tenant_id = :tenant_id AND tier_id = :tier_id AND deleted_at IS NULL'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'tier_id' => (int)$tier['id']]);
        $stats = $stmt->fetch() ?: [];

        $stmt = $pdo->prepare(
            'SELECT t.id, t.occurred_on, t.type, t.amount_cents, t.label, c.name AS category_name
             FROM treasury_transactions t
             LEFT JOIN treasury_categories c ON c.id = t.category_id AND c.tenant_id = t.tenant_id
             WHERE t.tenant_id = :tenant_id AND t.tier_id = :tier_id AND t.deleted_at IS NULL
             ORDER BY t.occurred_on DESC, t.id DESC
             LIMIT 25'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'tier_id' => (int)$tier['id']]);
        $transactions = $stmt->fetchAll();

        $flash = Session::flash('success');
        $error = Session::flash('error');

        require base_path('views/tiers/view.php');
    }

    public static function edit(): void
    {
        Access::require('treasury', 'write');

        $tenantId = (int)$_SESSION['tenant_id'];
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo '400';
            return;
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT id, name, member_id FROM tiers WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $tier = $stmt->fetch();
        if (!$tier) {
            http_response_code(404);
            echo '404';
            return;
        }

        $stmt = $pdo->prepare('SELECT tag FROM tier_tags WHERE tier_id = :tier_id ORDER BY tag ASC');
        $stmt->execute(['tier_id' => (int)$tier['id']]);
        $tags = array_map(static fn ($r) => (string)($r['tag'] ?? ''), $stmt->fetchAll());
        $tags = array_values(array_filter($tags, static fn ($t) => $t !== ''));

        $flash = Session::flash('success');
        $error = Session::flash('error');
        require base_path('views/tiers/edit.php');
    }

    public static function create(): void
    {
        Access::require('treasury', 'write');

        $error = Session::flash('error');
        require base_path('views/tiers/new.php');
    }

    public static function store(): void
    {
        Access::require('treasury', 'write');

        $tenantId = (int)$_SESSION['tenant_id'];
        $name = trim((string)($_POST['name'] ?? ''));
        $tagPartenaire = (int)($_POST['tag_partenaire'] ?? 0) === 1;
        $tagFournisseur = (int)($_POST['tag_fournisseur'] ?? 0) === 1;

        if ($name === '') {
            Session::flash('error', 'Nom invalide.');
            redirect(tenant_path('/tiers/new'));
        }
        if (mb_strlen($name) > 190) {
            $name = mb_substr($name, 0, 190);
        }

        $normalized = self::normalizeTierName($name);

        $pdo = Db::pdo();
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('INSERT INTO tiers (tenant_id, member_id, name, normalized_name) VALUES (:tenant_id, NULL, :name, :normalized_name)');
            $stmt->execute([
                'tenant_id' => $tenantId,
                'name' => $name,
                'normalized_name' => $normalized,
            ]);
            $tierId = (int)$pdo->lastInsertId();

            $tags = [];
            if ($tagPartenaire) {
                $tags[] = 'partenaire';
            }
            if ($tagFournisseur) {
                $tags[] = 'fournisseur';
            }

            if (!empty($tags) && $tierId > 0) {
                $stmt = $pdo->prepare('INSERT IGNORE INTO tier_tags (tier_id, tag) VALUES (:tier_id, :tag)');
                foreach ($tags as $tg) {
                    $stmt->execute(['tier_id' => $tierId, 'tag' => $tg]);
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Session::flash('error', 'Erreur lors de la création (nom déjà utilisé ?).');
            redirect(tenant_path('/tiers/new'));
        }

        Session::flash('success', 'Tiers ajouté.');
        redirect(tenant_path('/tiers'));
    }

    public static function addDocument(): void
    {
        Access::require('treasury', 'write');

        $tenantId = (int)$_SESSION['tenant_id'];
        $tierId = (int)($_POST['tier_id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $url = trim((string)($_POST['url'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        if ($tierId <= 0 || $title === '' || mb_strlen($title) > 190 || mb_strlen($url) > 500) {
            Session::flash('error', 'Document invalide.');
            redirect(tenant_path('/tiers'));
        }

        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT 1 FROM tiers WHERE tenant_id = :tenant_id AND id = :id LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId, 'id' => $tierId]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo '404';
            return;
        }

        $driver = 'local';
        $localPath = null;
        $gdriveFileId = null;
        $originalName = null;
        $mimeType = null;
        $sizeBytes = null;

        $file = $_FILES['file'] ?? null;
        if (is_array($file) && (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if ((int)($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                Session::flash('error', 'Upload invalide.');
                redirect(tenant_path('/tiers/view?id=' . $tierId));
            }

            $tmp = (string)($file['tmp_name'] ?? '');
            $orig = (string)($file['name'] ?? 'file');
            $size = (int)($file['size'] ?? 0);
            if ($tmp === '' || !is_file($tmp) || $size <= 0 || $size > self::MAX_DOC_FILE_SIZE) {
                Session::flash('error', 'Fichier invalide (max 10 Mo).');
                redirect(tenant_path('/tiers/view?id=' . $tierId));
            }

            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = (string)$finfo->file($tmp);
            if ($mime === '') {
                $mime = 'application/octet-stream';
            }

            $ext = pathinfo($orig, PATHINFO_EXTENSION);
            $ext = is_string($ext) ? strtolower(trim($ext)) : '';
            if ($ext === '') {
                $ext = match ($mime) {
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'application/pdf' => 'pdf',
                    default => 'bin',
                };
            }

            $useDrive = Modules::isEnabled($tenantId, 'drive')
                && GoogleDrive::isConfigured()
                && GoogleDrive::isAvailable()
                && GoogleDrive::isConnected($tenantId);

            $drive = $useDrive ? GoogleDrive::getService($tenantId) : null;
            $driveFolderId = $useDrive ? GoogleDrive::getDriveFolderId($tenantId) : null;
            $driveTargetFolderId = null;
            if ($drive !== null) {
                $driveTargetFolderId = GoogleDrive::ensureTierDocumentsFolder($tenantId, $driveFolderId, $tierId);
            }

            if ($drive !== null) {
                try {
                    $meta = ['name' => basename($orig)];
                    if ($driveTargetFolderId) {
                        $meta['parents'] = [$driveTargetFolderId];
                    } elseif ($driveFolderId) {
                        $meta['parents'] = [$driveFolderId];
                    }
                    $fileMetadata = new \Google_Service_Drive_DriveFile($meta);

                    $content = file_get_contents($tmp);
                    if ($content !== false) {
                        $created = $drive->files->create($fileMetadata, [
                            'data' => $content,
                            'mimeType' => $mime,
                            'uploadType' => 'multipart',
                            'fields' => 'id',
                        ]);
                        $fid = (string)($created->id ?? '');
                        if ($fid !== '') {
                            $driver = 'gdrive';
                            $gdriveFileId = $fid;
                            $originalName = $orig;
                            $mimeType = $mime;
                            $sizeBytes = $size;
                        }
                    }
                } catch (\Throwable $e) {
                    // fallback local
                }
            }

            if ($gdriveFileId === null) {
                $year = date('Y');
                $month = date('m');
                $dir = Storage::privatePath('tenant_' . $tenantId . '/ged/tiers/' . $year . '/' . $month . '/tier_' . $tierId);
                if (!is_dir($dir)) {
                    mkdir($dir, 0775, true);
                }

                $filename = bin2hex(random_bytes(16)) . '.' . $ext;
                $dest = $dir . DIRECTORY_SEPARATOR . $filename;
                if (!move_uploaded_file($tmp, $dest)) {
                    Session::flash('error', 'Impossible d\'enregistrer le fichier.');
                    redirect(tenant_path('/tiers/view?id=' . $tierId));
                }

                $driver = 'local';
                $localPath = 'tenant_' . $tenantId . '/ged/tiers/' . $year . '/' . $month . '/tier_' . $tierId . '/' . $filename;
                $originalName = $orig;
                $mimeType = $mime;
                $sizeBytes = $size;
            }
        }

        $stmt = $pdo->prepare(
            'INSERT INTO entity_documents (tenant_id, entity_type, entity_id, title, url, notes, storage_driver, local_path, gdrive_file_id, original_name, mime_type, size_bytes)
             VALUES (:tenant_id, :entity_type, :entity_id, :title, :url, :notes, :driver, :local_path, :gdrive_file_id, :original_name, :mime_type, :size_bytes)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'entity_type' => 'tier',
            'entity_id' => $tierId,
            'title' => $title,
            'url' => ($url !== '' ? $url : null),
            'notes' => ($notes !== '' ? $notes : null),
            'driver' => $driver,
            'local_path' => $localPath,
            'gdrive_file_id' => $gdriveFileId,
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
        ]);

        Session::flash('success', 'Document ajouté.');
        redirect(tenant_path('/tiers/view?id=' . $tierId));
    }

    public static function downloadDocument(): void
    {
        Access::require('treasury', 'read');

        $tenantId = (int)$_SESSION['tenant_id'];
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo '400';
            return;
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT id, entity_id, storage_driver, local_path, gdrive_file_id, original_name, mime_type, size_bytes FROM entity_documents WHERE tenant_id = :tenant_id AND id = :id AND entity_type = "tier" AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId, 'id' => $id]);
        $doc = $stmt->fetch();
        if (!$doc) {
            http_response_code(404);
            echo '404';
            return;
        }

        $driver = (string)($doc['storage_driver'] ?? 'local');
        if ($driver === 'gdrive') {
            if (!Modules::isEnabled($tenantId, 'drive')) {
                http_response_code(403);
                echo '403';
                return;
            }

            $service = GoogleDrive::getService($tenantId);
            if (!$service) {
                http_response_code(503);
                echo '503';
                return;
            }

            $fileId = (string)($doc['gdrive_file_id'] ?? '');
            if ($fileId === '') {
                http_response_code(404);
                echo '404';
                return;
            }

            $response = $service->files->get($fileId, ['alt' => 'media']);
            $body = $response->getBody();

            $mime = (string)($doc['mime_type'] ?? 'application/octet-stream');
            $name = (string)($doc['original_name'] ?? 'document');
            header('Content-Type: ' . $mime);
            header('Content-Disposition: attachment; filename="' . str_replace('"', '', $name) . '"');

            while (!$body->eof()) {
                echo $body->read(8192);
            }
            return;
        }

        $rel = (string)($doc['local_path'] ?? '');
        if ($rel === '') {
            http_response_code(404);
            echo '404';
            return;
        }

        $path = Storage::privatePath($rel);
        if (!is_file($path)) {
            http_response_code(404);
            echo '404';
            return;
        }

        $mime = (string)($doc['mime_type'] ?? 'application/octet-stream');
        $name = (string)($doc['original_name'] ?? 'document');
        header('Content-Type: ' . $mime);
        if (!empty($doc['size_bytes'])) {
            header('Content-Length: ' . (string)$doc['size_bytes']);
        }
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $name) . '"');
        readfile($path);
    }

    public static function deleteDocument(): void
    {
        Access::require('treasury', 'write');

        $tenantId = (int)$_SESSION['tenant_id'];
        $tierId = (int)($_POST['tier_id'] ?? 0);
        $id = (int)($_POST['id'] ?? 0);
        $reason = trim((string)($_POST['delete_reason'] ?? ''));

        if ($tierId <= 0 || $id <= 0) {
            Session::flash('error', 'Suppression invalide.');
            redirect(tenant_path('/tiers'));
        }

        if ($reason !== '' && mb_strlen($reason) > 500) {
            $reason = mb_substr($reason, 0, 500);
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT id, storage_driver, local_path, gdrive_file_id FROM entity_documents WHERE tenant_id = :tenant_id AND id = :id AND entity_type = "tier" AND entity_id = :tier_id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId, 'id' => $id, 'tier_id' => $tierId]);
        $doc = $stmt->fetch();
        if (!$doc) {
            Session::flash('error', 'Document introuvable.');
            redirect(tenant_path('/tiers/view?id=' . $tierId));
        }

        $driver = (string)($doc['storage_driver'] ?? 'local');
        $newLocalPath = null;
        $moved = false;

        if ($driver === 'gdrive') {
            try {
                if (Modules::isEnabled($tenantId, 'drive') && GoogleDrive::isConfigured() && GoogleDrive::isAvailable() && GoogleDrive::isConnected($tenantId)) {
                    $service = GoogleDrive::getService($tenantId);
                    $driveFolderId = GoogleDrive::getDriveFolderId($tenantId);
                    $trashFolderId = GoogleDrive::ensureTierDocumentsTrashFolder($tenantId, $driveFolderId, $tierId);
                    $fileId = (string)($doc['gdrive_file_id'] ?? '');
                    if ($service && $trashFolderId && $fileId !== '') {
                        $current = $service->files->get($fileId, ['fields' => 'parents']);
                        $parents = $current ? $current->getParents() : null;
                        $prevParents = is_array($parents) ? implode(',', $parents) : '';
                        $service->files->update($fileId, new \Google_Service_Drive_DriveFile(), [
                            'addParents' => $trashFolderId,
                            'removeParents' => $prevParents,
                            'fields' => 'id, parents',
                        ]);
                        $moved = true;
                    }
                }
            } catch (\Throwable $e) {
                $moved = false;
            }
        } else {
            $rel = (string)($doc['local_path'] ?? '');
            if ($rel !== '') {
                $src = Storage::privatePath($rel);
                if (is_file($src)) {
                    $trashRelDir = 'tenant_' . $tenantId . '/ged/tiers/_trash/tier_' . $tierId;
                    $trashDir = Storage::privatePath($trashRelDir);
                    if (!is_dir($trashDir)) {
                        mkdir($trashDir, 0775, true);
                    }
                    $base = basename($src);
                    $destName = date('Ymd_His') . '_' . $id . '_' . $base;
                    $dest = $trashDir . DIRECTORY_SEPARATOR . $destName;
                    if (@rename($src, $dest)) {
                        $newLocalPath = $trashRelDir . '/' . $destName;
                        $moved = true;
                    }
                }
            }
        }

        $stmt = $pdo->prepare(
            'UPDATE entity_documents
             SET deleted_at = NOW(),
                 deleted_by_user_id = :uid,
                 delete_reason = :reason,
                 local_path = COALESCE(:new_local_path, local_path)
             WHERE tenant_id = :tenant_id AND id = :id AND entity_type = "tier" AND entity_id = :tier_id AND deleted_at IS NULL'
        );
        $stmt->execute([
            'uid' => (int)($_SESSION['user_id'] ?? 0) ?: null,
            'reason' => ($reason !== '' ? $reason : null),
            'new_local_path' => $newLocalPath,
            'tenant_id' => $tenantId,
            'id' => $id,
            'tier_id' => $tierId,
        ]);

        Session::flash('success', $moved ? 'Document déplacé en corbeille.' : 'Document supprimé (fichier non déplacé).');
        redirect(tenant_path('/tiers/view?id=' . $tierId));
    }

    public static function update(): void
    {
        Access::require('treasury', 'write');

        $tenantId = (int)$_SESSION['tenant_id'];
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo '400';
            return;
        }

        $tagAdherent = (int)($_POST['tag_adherent'] ?? 0) === 1;
        $tagPartenaire = (int)($_POST['tag_partenaire'] ?? 0) === 1;
        $tagFournisseur = (int)($_POST['tag_fournisseur'] ?? 0) === 1;

        $pdo = Db::pdo();

        $stmt = $pdo->prepare('SELECT id, member_id FROM tiers WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $tier = $stmt->fetch();
        if (!$tier) {
            http_response_code(404);
            echo '404';
            return;
        }

        $memberId = (int)($tier['member_id'] ?? 0);
        $isLinkedToMember = $memberId > 0;

        $name = trim((string)($_POST['name'] ?? ''));
        if (!$isLinkedToMember) {
            if ($name === '') {
                Session::flash('error', 'Nom invalide.');
                redirect(tenant_path('/tiers/edit?id=' . $id));
            }
            if (mb_strlen($name) > 190) {
                $name = mb_substr($name, 0, 190);
            }
        }

        $desiredTags = [];
        if ($tagAdherent) {
            $desiredTags[] = 'adherent';
        }
        if ($tagPartenaire) {
            $desiredTags[] = 'partenaire';
        }
        if ($tagFournisseur) {
            $desiredTags[] = 'fournisseur';
        }

        if ($isLinkedToMember && !in_array('adherent', $desiredTags, true)) {
            $desiredTags[] = 'adherent';
        }

        try {
            $pdo->beginTransaction();

            if (!$isLinkedToMember) {
                $stmt = $pdo->prepare('UPDATE tiers SET name = :name, normalized_name = :normalized_name WHERE id = :id AND tenant_id = :tenant_id');
                $stmt->execute([
                    'name' => $name,
                    'normalized_name' => self::normalizeTierName($name),
                    'id' => $id,
                    'tenant_id' => $tenantId,
                ]);
            }

            $allowedTags = ['adherent', 'partenaire', 'fournisseur'];
            $stmt = $pdo->prepare('DELETE FROM tier_tags WHERE tier_id = :tier_id AND tag IN ("adherent", "partenaire", "fournisseur")');
            $stmt->execute(['tier_id' => $id]);

            $stmtIns = $pdo->prepare('INSERT IGNORE INTO tier_tags (tier_id, tag) VALUES (:tier_id, :tag)');
            foreach ($desiredTags as $tg) {
                if (!in_array($tg, $allowedTags, true)) {
                    continue;
                }
                $stmtIns->execute(['tier_id' => $id, 'tag' => $tg]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Session::flash('error', 'Erreur lors de la mise à jour.');
            redirect(tenant_path('/tiers/edit?id=' . $id));
        }

        Session::flash('success', 'Tiers mis à jour.');
        redirect(tenant_path('/tiers/view?id=' . $id));
    }

    public static function syncMembers(): void
    {
        Access::require('members', 'write');

        $tenantId = (int)$_SESSION['tenant_id'];
        if ($tenantId <= 0) {
            Session::flash('error', 'Tenant invalide.');
            redirect('/tiers');
        }

        $pdo = Db::pdo();

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'UPDATE tier_tags tt
                 INNER JOIN tiers t ON t.id = tt.tier_id
                 SET tt.tag = "adherent"
                 WHERE t.tenant_id = :tenant_id AND tt.tag = "member"'
            );
            $stmt->execute(['tenant_id' => $tenantId]);

            $stmt = $pdo->prepare('SELECT id, first_name, last_name FROM members WHERE tenant_id = :tenant_id ORDER BY id ASC');
            $stmt->execute(['tenant_id' => $tenantId]);
            $members = $stmt->fetchAll();

            foreach ($members as $m) {
                $mid = (int)($m['id'] ?? 0);
                if ($mid <= 0) {
                    continue;
                }
                $displayName = trim((string)($m['first_name'] ?? '') . ' ' . (string)($m['last_name'] ?? ''));
                self::upsertMemberTier($pdo, $tenantId, $mid, $displayName);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Session::flash('error', 'Erreur lors de la synchronisation.');
            redirect('/tiers');
        }

        Session::flash('success', 'Synchronisation terminée.');
        redirect('/tiers');
    }
}
