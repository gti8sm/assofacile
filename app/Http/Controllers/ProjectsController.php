<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Db;
use App\Support\Access;
use App\Support\Session;
use App\Support\GoogleDrive;
use App\Support\Modules;
use App\Support\Storage;

final class ProjectsController
{
    private const MAX_DOC_FILE_SIZE = 10485760; // 10 MB

    private static function normalizeTierName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        $name = mb_strtolower($name);
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;
        $name = trim($name);
        if (mb_strlen($name) > 190) {
            $name = mb_substr($name, 0, 190);
        }

        return $name;
    }

    public static function index(): void
    {
        Access::require('projects', 'read');

        $tenantId = (int)$_SESSION['tenant_id'];
        $pdo = Db::pdo();

        $stmt = $pdo->prepare('SELECT id, name, status, starts_on, ends_on, created_at FROM projects WHERE tenant_id = :tenant_id ORDER BY created_at DESC');
        $stmt->execute(['tenant_id' => $tenantId]);
        $projects = $stmt->fetchAll();

        $flash = Session::flash('success');
        $error = Session::flash('error');

        require base_path('views/projects/index.php');
    }

    public static function create(): void
    {
        Access::require('projects', 'write');

        $flash = Session::flash('success');
        $error = Session::flash('error');

        require base_path('views/projects/new.php');
    }

    public static function store(): void
    {
        Access::require('projects', 'write');

        $tenantId = (int)$_SESSION['tenant_id'];
        $userId = (int)$_SESSION['user_id'];

        $name = trim((string)($_POST['name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $fundingType = trim((string)($_POST['funding_type'] ?? ''));
        $startsOn = trim((string)($_POST['starts_on'] ?? ''));
        $endsOn = trim((string)($_POST['ends_on'] ?? ''));

        if ($name === '' || mb_strlen($name) > 190) {
            Session::flash('error', 'Nom invalide.');
            redirect(tenant_path('/projects/new'));
        }

        if ($fundingType !== '' && mb_strlen($fundingType) > 64) {
            $fundingType = mb_substr($fundingType, 0, 64);
        }

        if ($startsOn !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startsOn)) {
            $startsOn = '';
        }
        if ($endsOn !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endsOn)) {
            $endsOn = '';
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO projects (tenant_id, name, description, funding_type, starts_on, ends_on, status, created_by_user_id)
             VALUES (:tenant_id, :name, :description, :funding_type, :starts_on, :ends_on, :status, :created_by_user_id)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'name' => $name,
            'description' => ($description !== '' ? $description : null),
            'funding_type' => ($fundingType !== '' ? $fundingType : null),
            'starts_on' => ($startsOn !== '' ? $startsOn : null),
            'ends_on' => ($endsOn !== '' ? $endsOn : null),
            'status' => 'active',
            'created_by_user_id' => $userId > 0 ? $userId : null,
        ]);

        $projectId = (int)$pdo->lastInsertId();

        Session::flash('success', 'Projet créé.');
        redirect(tenant_path('/projects/view?id=' . $projectId));
    }

    public static function view(): void
    {
        Access::require('projects', 'read');

        $tenantId = (int)$_SESSION['tenant_id'];
        $projectId = (int)($_GET['id'] ?? 0);
        if ($projectId <= 0) {
            http_response_code(400);
            echo '400';
            return;
        }

        $pdo = Db::pdo();

        $stmt = $pdo->prepare('SELECT * FROM projects WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
        $stmt->execute(['id' => $projectId, 'tenant_id' => $tenantId]);
        $project = $stmt->fetch();
        if (!$project) {
            http_response_code(404);
            echo '404';
            return;
        }

        $stmt = $pdo->prepare('SELECT * FROM project_actions WHERE tenant_id = :tenant_id AND project_id = :project_id ORDER BY id ASC');
        $stmt->execute(['tenant_id' => $tenantId, 'project_id' => $projectId]);
        $actions = $stmt->fetchAll();

        $stmt = $pdo->prepare(
            'SELECT pt.*, pa.name AS action_name
             FROM project_tasks pt
             LEFT JOIN project_actions pa ON pa.id = pt.action_id AND pa.tenant_id = pt.tenant_id
             WHERE pt.tenant_id = :tenant_id AND pt.project_id = :project_id
             ORDER BY pt.due_on IS NULL, pt.due_on ASC, pt.id ASC'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'project_id' => $projectId]);
        $tasks = $stmt->fetchAll();

        $stmt = $pdo->prepare(
            'SELECT pp.*, t.name AS tier_name, u.full_name AS user_name, pa.name AS action_name
             FROM project_participants pp
             LEFT JOIN tiers t ON t.id = pp.tier_id AND t.tenant_id = pp.tenant_id
             LEFT JOIN users u ON u.id = pp.user_id AND u.tenant_id = pp.tenant_id
             LEFT JOIN project_actions pa ON pa.id = pp.action_id AND pa.tenant_id = pp.tenant_id
             WHERE pp.tenant_id = :tenant_id AND pp.project_id = :project_id
             ORDER BY pp.role ASC, pp.id ASC'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'project_id' => $projectId]);
        $participants = $stmt->fetchAll();

        $treasuryBudgets = [];
        $treasuryTransactions = [];
        try {
            $stmt = $pdo->prepare(
                'SELECT id, name, is_active
                 FROM treasury_budgets
                 WHERE tenant_id = :tenant_id AND id IN (
                     SELECT budget_id
                     FROM treasury_budget_projects
                     WHERE tenant_id = :tenant_id AND project_id = :project_id
                 )
                 ORDER BY is_active DESC, name ASC'
            );
            $stmt->execute(['tenant_id' => $tenantId, 'project_id' => $projectId]);
            $treasuryBudgets = $stmt->fetchAll();

            $stmt = $pdo->prepare(
                'SELECT tt.id, tt.type, tt.amount_cents, tt.label, tt.occurred_on, SUM(a.amount_cents) AS allocated_cents
                 FROM treasury_budget_allocations a
                 JOIN treasury_budgets b ON b.id = a.budget_id AND b.tenant_id = a.tenant_id
                 JOIN treasury_budget_projects bp ON bp.budget_id = b.id AND bp.tenant_id = b.tenant_id
                 JOIN treasury_transactions tt ON tt.id = a.transaction_id AND tt.tenant_id = a.tenant_id
                 WHERE a.tenant_id = :tenant_id
                   AND bp.project_id = :project_id
                   AND tt.deleted_at IS NULL
                 GROUP BY tt.id, tt.type, tt.amount_cents, tt.label, tt.occurred_on
                 ORDER BY tt.occurred_on DESC, tt.id DESC'
            );
            $stmt->execute(['tenant_id' => $tenantId, 'project_id' => $projectId]);
            $treasuryTransactions = $stmt->fetchAll();
        } catch (\Throwable $e) {
            $treasuryBudgets = [];
            $treasuryTransactions = [];
        }

        $links = [];
        $documents = [];
        try {
            $stmt = $pdo->prepare('SELECT id, label, url, created_at FROM project_links WHERE tenant_id = :tenant_id AND project_id = :project_id ORDER BY id DESC');
            $stmt->execute(['tenant_id' => $tenantId, 'project_id' => $projectId]);
            $links = $stmt->fetchAll();

            $stmt = $pdo->prepare('SELECT id, title, url, notes, storage_driver, local_path, gdrive_file_id, original_name, mime_type, size_bytes, created_at FROM project_documents WHERE tenant_id = :tenant_id AND project_id = :project_id AND deleted_at IS NULL ORDER BY id DESC');
            $stmt->execute(['tenant_id' => $tenantId, 'project_id' => $projectId]);
            $documents = $stmt->fetchAll();
        } catch (\Throwable $e) {
            $links = [];
            $documents = [];
        }

        $flash = Session::flash('success');
        $error = Session::flash('error');

        require base_path('views/projects/view.php');
    }

    public static function addLink(): void
    {
        Access::require('projects', 'write');

        $tenantId = (int)$_SESSION['tenant_id'];
        $projectId = (int)($_POST['project_id'] ?? 0);
        $label = trim((string)($_POST['label'] ?? ''));
        $url = trim((string)($_POST['url'] ?? ''));

        if ($projectId <= 0 || $label === '' || mb_strlen($label) > 190 || $url === '' || mb_strlen($url) > 500) {
            Session::flash('error', 'Lien invalide.');
            redirect(tenant_path('/projects'));
        }

        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT 1 FROM projects WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
        $stmt->execute(['id' => $projectId, 'tenant_id' => $tenantId]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo '404';
            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO project_links (tenant_id, project_id, label, url)
             VALUES (:tenant_id, :project_id, :label, :url)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'project_id' => $projectId,
            'label' => $label,
            'url' => $url,
        ]);

        Session::flash('success', 'Lien ajouté.');
        redirect(tenant_path('/projects/view?id=' . $projectId . '#links'));
    }

    public static function deleteLink(): void
    {
        Access::require('projects', 'write');

        $tenantId = (int)$_SESSION['tenant_id'];
        $projectId = (int)($_POST['project_id'] ?? 0);
        $id = (int)($_POST['id'] ?? 0);
        if ($projectId <= 0 || $id <= 0) {
            Session::flash('error', 'Suppression invalide.');
            redirect(tenant_path('/projects'));
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('DELETE FROM project_links WHERE tenant_id = :tenant_id AND project_id = :project_id AND id = :id');
        $stmt->execute(['tenant_id' => $tenantId, 'project_id' => $projectId, 'id' => $id]);

        Session::flash('success', 'Lien supprimé.');
        redirect(tenant_path('/projects/view?id=' . $projectId . '#links'));
    }

    public static function addDocument(): void
    {
        Access::require('projects', 'write');

        $tenantId = (int)$_SESSION['tenant_id'];
        $projectId = (int)($_POST['project_id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $url = trim((string)($_POST['url'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        if ($projectId <= 0 || $title === '' || mb_strlen($title) > 190 || mb_strlen($url) > 500) {
            Session::flash('error', 'Document invalide.');
            redirect(tenant_path('/projects'));
        }

        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT 1 FROM projects WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
        $stmt->execute(['id' => $projectId, 'tenant_id' => $tenantId]);
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
                redirect(tenant_path('/projects/view?id=' . $projectId . '#docs'));
            }

            $tmp = (string)($file['tmp_name'] ?? '');
            $orig = (string)($file['name'] ?? 'file');
            $size = (int)($file['size'] ?? 0);
            if ($tmp === '' || !is_file($tmp) || $size <= 0 || $size > self::MAX_DOC_FILE_SIZE) {
                Session::flash('error', 'Fichier invalide (max 10 Mo).');
                redirect(tenant_path('/projects/view?id=' . $projectId . '#docs'));
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
                $driveTargetFolderId = GoogleDrive::ensureProjectDocumentsFolder($tenantId, $driveFolderId, $projectId);
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
                $dir = Storage::privatePath('tenant_' . $tenantId . '/ged/projects/' . $year . '/' . $month . '/project_' . $projectId);
                if (!is_dir($dir)) {
                    mkdir($dir, 0775, true);
                }

                $filename = bin2hex(random_bytes(16)) . '.' . $ext;
                $dest = $dir . DIRECTORY_SEPARATOR . $filename;
                if (!move_uploaded_file($tmp, $dest)) {
                    Session::flash('error', 'Impossible d\'enregistrer le fichier.');
                    redirect(tenant_path('/projects/view?id=' . $projectId . '#docs'));
                }

                $driver = 'local';
                $localPath = 'tenant_' . $tenantId . '/ged/projects/' . $year . '/' . $month . '/project_' . $projectId . '/' . $filename;
                $originalName = $orig;
                $mimeType = $mime;
                $sizeBytes = $size;
            }
        }

        $stmt = $pdo->prepare(
            'INSERT INTO project_documents (tenant_id, project_id, title, url, notes, storage_driver, local_path, gdrive_file_id, original_name, mime_type, size_bytes)
             VALUES (:tenant_id, :project_id, :title, :url, :notes, :driver, :local_path, :gdrive_file_id, :original_name, :mime_type, :size_bytes)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'project_id' => $projectId,
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
        redirect(tenant_path('/projects/view?id=' . $projectId . '#docs'));
    }

    public static function downloadDocument(): void
    {
        Access::require('projects', 'read');

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo '400';
            return;
        }

        $tenantId = (int)$_SESSION['tenant_id'];
        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT id, project_id, storage_driver, local_path, gdrive_file_id, original_name, mime_type, size_bytes FROM project_documents WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
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
        Access::require('projects', 'write');

        $tenantId = (int)$_SESSION['tenant_id'];
        $projectId = (int)($_POST['project_id'] ?? 0);
        $id = (int)($_POST['id'] ?? 0);
        $reason = trim((string)($_POST['delete_reason'] ?? ''));
        if ($projectId <= 0 || $id <= 0) {
            Session::flash('error', 'Suppression invalide.');
            redirect(tenant_path('/projects'));
        }

        if ($reason !== '' && mb_strlen($reason) > 500) {
            $reason = mb_substr($reason, 0, 500);
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT id, storage_driver, local_path, gdrive_file_id FROM project_documents WHERE tenant_id = :tenant_id AND project_id = :project_id AND id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId, 'project_id' => $projectId, 'id' => $id]);
        $doc = $stmt->fetch();
        if (!$doc) {
            Session::flash('error', 'Document introuvable.');
            redirect(tenant_path('/projects/view?id=' . $projectId . '#docs'));
        }

        $driver = (string)($doc['storage_driver'] ?? 'local');
        $newLocalPath = null;
        $moved = false;

        if ($driver === 'gdrive') {
            try {
                if (Modules::isEnabled($tenantId, 'drive') && GoogleDrive::isConfigured() && GoogleDrive::isAvailable() && GoogleDrive::isConnected($tenantId)) {
                    $service = GoogleDrive::getService($tenantId);
                    $driveFolderId = GoogleDrive::getDriveFolderId($tenantId);
                    $trashFolderId = GoogleDrive::ensureProjectDocumentsTrashFolder($tenantId, $driveFolderId, $projectId);
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
                    $trashRelDir = 'tenant_' . $tenantId . '/ged/projects/_trash/project_' . $projectId;
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
            'UPDATE project_documents
             SET deleted_at = NOW(),
                 deleted_by_user_id = :uid,
                 delete_reason = :reason,
                 local_path = COALESCE(:new_local_path, local_path)
             WHERE tenant_id = :tenant_id AND project_id = :project_id AND id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([
            'uid' => (int)($_SESSION['user_id'] ?? 0) ?: null,
            'reason' => ($reason !== '' ? $reason : null),
            'new_local_path' => $newLocalPath,
            'tenant_id' => $tenantId,
            'project_id' => $projectId,
            'id' => $id,
        ]);

        Session::flash('success', $moved ? 'Document déplacé en corbeille.' : 'Document supprimé (fichier non déplacé).');
        redirect(tenant_path('/projects/view?id=' . $projectId . '#docs'));
    }

    public static function edit(): void
    {
        Access::require('projects', 'write');

        $tenantId = (int)$_SESSION['tenant_id'];
        $projectId = (int)($_GET['id'] ?? 0);
        if ($projectId <= 0) {
            http_response_code(400);
            echo '400';
            return;
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT * FROM projects WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
        $stmt->execute(['id' => $projectId, 'tenant_id' => $tenantId]);
        $project = $stmt->fetch();
        if (!$project) {
            http_response_code(404);
            echo '404';
            return;
        }

        $flash = Session::flash('success');
        $error = Session::flash('error');
        require base_path('views/projects/edit.php');
    }

    public static function update(): void
    {
        Access::require('projects', 'write');

        $tenantId = (int)$_SESSION['tenant_id'];
        $projectId = (int)($_POST['id'] ?? 0);
        if ($projectId <= 0) {
            http_response_code(400);
            echo '400';
            return;
        }

        $name = trim((string)($_POST['name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $fundingType = trim((string)($_POST['funding_type'] ?? ''));
        $startsOn = trim((string)($_POST['starts_on'] ?? ''));
        $endsOn = trim((string)($_POST['ends_on'] ?? ''));
        $status = trim((string)($_POST['status'] ?? 'active'));

        if ($name === '' || mb_strlen($name) > 190) {
            Session::flash('error', 'Nom invalide.');
            redirect(tenant_path('/projects/edit?id=' . $projectId));
        }
        if ($fundingType !== '' && mb_strlen($fundingType) > 64) {
            $fundingType = mb_substr($fundingType, 0, 64);
        }
        if ($startsOn !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startsOn)) {
            $startsOn = '';
        }
        if ($endsOn !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endsOn)) {
            $endsOn = '';
        }
        if (!in_array($status, ['active', 'completed', 'archived'], true)) {
            $status = 'active';
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT 1 FROM projects WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
        $stmt->execute(['id' => $projectId, 'tenant_id' => $tenantId]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo '404';
            return;
        }

        $stmt = $pdo->prepare(
            'UPDATE projects
             SET name = :name,
                 description = :description,
                 funding_type = :funding_type,
                 starts_on = :starts_on,
                 ends_on = :ends_on,
                 status = :status
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute([
            'name' => $name,
            'description' => ($description !== '' ? $description : null),
            'funding_type' => ($fundingType !== '' ? $fundingType : null),
            'starts_on' => ($startsOn !== '' ? $startsOn : null),
            'ends_on' => ($endsOn !== '' ? $endsOn : null),
            'status' => $status,
            'id' => $projectId,
            'tenant_id' => $tenantId,
        ]);

        Session::flash('success', 'Projet mis à jour.');
        redirect(tenant_path('/projects/view?id=' . $projectId));
    }

    public static function editAction(): void
    {
        Access::require('projects', 'write');

        $tenantId = (int)$_SESSION['tenant_id'];
        $id = (int)($_GET['id'] ?? 0);
        $projectId = (int)($_GET['project_id'] ?? 0);
        if ($id <= 0 || $projectId <= 0) {
            http_response_code(400);
            echo '400';
            return;
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT * FROM project_actions WHERE id = :id AND tenant_id = :tenant_id AND project_id = :project_id LIMIT 1');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId, 'project_id' => $projectId]);
        $action = $stmt->fetch();
        if (!$action) {
            http_response_code(404);
            echo '404';
            return;
        }

        $flash = Session::flash('success');
        $error = Session::flash('error');
        require base_path('views/projects/action_edit.php');
    }

    public static function updateAction(): void
    {
        Access::require('projects', 'write');

        $tenantId = (int)$_SESSION['tenant_id'];
        $id = (int)($_POST['id'] ?? 0);
        $projectId = (int)($_POST['project_id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $scheduleRule = trim((string)($_POST['schedule_rule'] ?? ''));

        if ($id <= 0 || $projectId <= 0 || $name === '' || mb_strlen($name) > 190) {
            Session::flash('error', 'Action invalide.');
            redirect(tenant_path('/projects/view?id=' . $projectId));
        }
        if ($scheduleRule !== '' && mb_strlen($scheduleRule) > 64) {
            $scheduleRule = mb_substr($scheduleRule, 0, 64);
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare(
            'UPDATE project_actions
             SET name = :name, schedule_rule = :schedule_rule
             WHERE id = :id AND tenant_id = :tenant_id AND project_id = :project_id'
        );
        $stmt->execute([
            'name' => $name,
            'schedule_rule' => ($scheduleRule !== '' ? $scheduleRule : null),
            'id' => $id,
            'tenant_id' => $tenantId,
            'project_id' => $projectId,
        ]);

        Session::flash('success', 'Action mise à jour.');
        redirect(tenant_path('/projects/view?id=' . $projectId));
    }

    public static function editTask(): void
    {
        Access::require('projects', 'write');

        $tenantId = (int)$_SESSION['tenant_id'];
        $id = (int)($_GET['id'] ?? 0);
        $projectId = (int)($_GET['project_id'] ?? 0);
        if ($id <= 0 || $projectId <= 0) {
            http_response_code(400);
            echo '400';
            return;
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT * FROM projects WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
        $stmt->execute(['id' => $projectId, 'tenant_id' => $tenantId]);
        $project = $stmt->fetch();
        if (!$project) {
            http_response_code(404);
            echo '404';
            return;
        }

        $stmt = $pdo->prepare('SELECT * FROM project_tasks WHERE id = :id AND tenant_id = :tenant_id AND project_id = :project_id LIMIT 1');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId, 'project_id' => $projectId]);
        $task = $stmt->fetch();
        if (!$task) {
            http_response_code(404);
            echo '404';
            return;
        }

        $stmt = $pdo->prepare('SELECT id, name FROM project_actions WHERE tenant_id = :tenant_id AND project_id = :project_id ORDER BY id ASC');
        $stmt->execute(['tenant_id' => $tenantId, 'project_id' => $projectId]);
        $actions = $stmt->fetchAll();

        $flash = Session::flash('success');
        $error = Session::flash('error');
        require base_path('views/projects/task_edit.php');
    }

    public static function updateTask(): void
    {
        Access::require('projects', 'write');

        $tenantId = (int)$_SESSION['tenant_id'];
        $id = (int)($_POST['id'] ?? 0);
        $projectId = (int)($_POST['project_id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $dueOn = trim((string)($_POST['due_on'] ?? ''));
        $status = trim((string)($_POST['status'] ?? 'todo'));
        $actionId = (int)($_POST['action_id'] ?? 0);

        if ($id <= 0 || $projectId <= 0 || $title === '' || mb_strlen($title) > 190) {
            Session::flash('error', 'Tâche invalide.');
            redirect(tenant_path('/projects/view?id=' . $projectId));
        }
        if ($dueOn !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueOn)) {
            $dueOn = '';
        }
        if (!in_array($status, ['todo', 'doing', 'done'], true)) {
            $status = 'todo';
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT 1 FROM project_tasks WHERE id = :id AND tenant_id = :tenant_id AND project_id = :project_id LIMIT 1');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId, 'project_id' => $projectId]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo '404';
            return;
        }

        $actionIdVal = $actionId > 0 ? $actionId : null;
        if ($actionIdVal !== null) {
            $stmt = $pdo->prepare('SELECT 1 FROM project_actions WHERE id = :id AND tenant_id = :tenant_id AND project_id = :project_id LIMIT 1');
            $stmt->execute(['id' => $actionIdVal, 'tenant_id' => $tenantId, 'project_id' => $projectId]);
            if (!$stmt->fetch()) {
                $actionIdVal = null;
            }
        }

        $stmt = $pdo->prepare(
            'UPDATE project_tasks
             SET title = :title,
                 status = :status,
                 due_on = :due_on,
                 action_id = :action_id
             WHERE id = :id AND tenant_id = :tenant_id AND project_id = :project_id'
        );
        $stmt->execute([
            'title' => $title,
            'status' => $status,
            'due_on' => ($dueOn !== '' ? $dueOn : null),
            'action_id' => $actionIdVal,
            'id' => $id,
            'tenant_id' => $tenantId,
            'project_id' => $projectId,
        ]);

        Session::flash('success', 'Tâche mise à jour.');
        redirect(tenant_path('/projects/view?id=' . $projectId));
    }

    public static function tiersSearch(): void
    {
        Access::require('projects', 'read');

        $tenantId = (int)($_SESSION['tenant_id'] ?? 0);
        if ($tenantId <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['items' => []]);
            return;
        }

        $q = trim((string)($_GET['q'] ?? ''));
        if ($q === '') {
            header('Content-Type: application/json');
            echo json_encode(['items' => []]);
            return;
        }

        if (mb_strlen($q) > 64) {
            $q = mb_substr($q, 0, 64);
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare(
            'SELECT id, name
             FROM tiers
             WHERE tenant_id = :tenant_id
               AND (name LIKE :q OR normalized_name LIKE :q2)
             ORDER BY name ASC
             LIMIT 10'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'q' => '%' . $q . '%',
            'q2' => '%' . self::normalizeTierName($q) . '%',
        ]);
        $rows = $stmt->fetchAll();

        $items = [];
        foreach ($rows as $r) {
            $items[] = [
                'id' => (int)($r['id'] ?? 0),
                'name' => (string)($r['name'] ?? ''),
            ];
        }

        header('Content-Type: application/json');
        echo json_encode(['items' => $items]);
    }

    public static function addAction(): void
    {
        Access::require('projects', 'write');

        $tenantId = (int)$_SESSION['tenant_id'];
        $projectId = (int)($_POST['project_id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $scheduleRule = trim((string)($_POST['schedule_rule'] ?? ''));

        if ($projectId <= 0 || $name === '' || mb_strlen($name) > 190) {
            Session::flash('error', 'Action invalide.');
            redirect(tenant_path('/projects'));
        }

        if ($scheduleRule !== '' && mb_strlen($scheduleRule) > 64) {
            $scheduleRule = mb_substr($scheduleRule, 0, 64);
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT 1 FROM projects WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
        $stmt->execute(['id' => $projectId, 'tenant_id' => $tenantId]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo '404';
            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO project_actions (tenant_id, project_id, name, schedule_rule)
             VALUES (:tenant_id, :project_id, :name, :schedule_rule)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'project_id' => $projectId,
            'name' => $name,
            'schedule_rule' => ($scheduleRule !== '' ? $scheduleRule : null),
        ]);

        Session::flash('success', 'Action ajoutée.');
        redirect(tenant_path('/projects/view?id=' . $projectId));
    }

    public static function addTask(): void
    {
        Access::require('projects', 'write');

        $tenantId = (int)$_SESSION['tenant_id'];
        $projectId = (int)($_POST['project_id'] ?? 0);
        $actionId = (int)($_POST['action_id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $dueOn = trim((string)($_POST['due_on'] ?? ''));

        if ($projectId <= 0 || $title === '' || mb_strlen($title) > 190) {
            Session::flash('error', 'Tâche invalide.');
            redirect(tenant_path('/projects'));
        }

        if ($dueOn !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueOn)) {
            $dueOn = '';
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT 1 FROM projects WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
        $stmt->execute(['id' => $projectId, 'tenant_id' => $tenantId]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo '404';
            return;
        }

        $actionIdVal = $actionId > 0 ? $actionId : null;
        if ($actionIdVal !== null) {
            $stmt = $pdo->prepare('SELECT 1 FROM project_actions WHERE id = :id AND tenant_id = :tenant_id AND project_id = :project_id LIMIT 1');
            $stmt->execute(['id' => $actionIdVal, 'tenant_id' => $tenantId, 'project_id' => $projectId]);
            if (!$stmt->fetch()) {
                $actionIdVal = null;
            }
        }

        $stmt = $pdo->prepare(
            'INSERT INTO project_tasks (tenant_id, project_id, action_id, title, status, due_on)
             VALUES (:tenant_id, :project_id, :action_id, :title, :status, :due_on)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'project_id' => $projectId,
            'action_id' => $actionIdVal,
            'title' => $title,
            'status' => 'todo',
            'due_on' => ($dueOn !== '' ? $dueOn : null),
        ]);

        Session::flash('success', 'Tâche ajoutée.');
        redirect(tenant_path('/projects/view?id=' . $projectId));
    }

    public static function addParticipant(): void
    {
        Access::require('projects', 'write');

        $tenantId = (int)$_SESSION['tenant_id'];
        $projectId = (int)($_POST['project_id'] ?? 0);
        $actionId = (int)($_POST['action_id'] ?? 0);
        $tierId = (int)($_POST['tier_id'] ?? 0);
        $role = trim((string)($_POST['role'] ?? ''));

        if ($projectId <= 0 || $tierId <= 0 || $role === '' || mb_strlen($role) > 64) {
            Session::flash('error', 'Participant invalide.');
            redirect(tenant_path('/projects'));
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT 1 FROM projects WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
        $stmt->execute(['id' => $projectId, 'tenant_id' => $tenantId]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo '404';
            return;
        }

        $stmt = $pdo->prepare('SELECT 1 FROM tiers WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
        $stmt->execute(['id' => $tierId, 'tenant_id' => $tenantId]);
        if (!$stmt->fetch()) {
            Session::flash('error', 'Tiers introuvable.');
            redirect(tenant_path('/projects/view?id=' . $projectId));
        }

        $actionIdVal = $actionId > 0 ? $actionId : null;
        if ($actionIdVal !== null) {
            $stmt = $pdo->prepare('SELECT 1 FROM project_actions WHERE id = :id AND tenant_id = :tenant_id AND project_id = :project_id LIMIT 1');
            $stmt->execute(['id' => $actionIdVal, 'tenant_id' => $tenantId, 'project_id' => $projectId]);
            if (!$stmt->fetch()) {
                $actionIdVal = null;
            }
        }

        $stmt = $pdo->prepare(
            'INSERT INTO project_participants (tenant_id, project_id, action_id, tier_id, role)
             VALUES (:tenant_id, :project_id, :action_id, :tier_id, :role)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'project_id' => $projectId,
            'action_id' => $actionIdVal,
            'tier_id' => $tierId,
            'role' => $role,
        ]);

        Session::flash('success', 'Participant ajouté.');
        redirect(tenant_path('/projects/view?id=' . $projectId));
    }

    public static function duplicate(): void
    {
        Access::require('projects', 'write');

        $tenantId = (int)$_SESSION['tenant_id'];
        $userId = (int)$_SESSION['user_id'];
        $projectId = (int)($_POST['project_id'] ?? 0);
        if ($projectId <= 0) {
            http_response_code(400);
            echo '400';
            return;
        }

        $pdo = Db::pdo();

        $stmt = $pdo->prepare('SELECT * FROM projects WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
        $stmt->execute(['id' => $projectId, 'tenant_id' => $tenantId]);
        $project = $stmt->fetch();
        if (!$project) {
            http_response_code(404);
            echo '404';
            return;
        }

        $pdo->beginTransaction();
        try {
            $newName = (string)$project['name'] . ' (copie)';
            if (mb_strlen($newName) > 190) {
                $newName = mb_substr($newName, 0, 190);
            }

            $stmt = $pdo->prepare(
                'INSERT INTO projects (tenant_id, name, description, funding_type, starts_on, ends_on, status, created_by_user_id)
                 VALUES (:tenant_id, :name, :description, :funding_type, NULL, NULL, :status, :created_by_user_id)'
            );
            $stmt->execute([
                'tenant_id' => $tenantId,
                'name' => $newName,
                'description' => $project['description'] ?? null,
                'funding_type' => $project['funding_type'] ?? null,
                'status' => 'active',
                'created_by_user_id' => $userId > 0 ? $userId : null,
            ]);
            $newProjectId = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare('SELECT * FROM project_actions WHERE tenant_id = :tenant_id AND project_id = :project_id ORDER BY id ASC');
            $stmt->execute(['tenant_id' => $tenantId, 'project_id' => $projectId]);
            $oldActions = $stmt->fetchAll();

            $actionIdMap = [];
            foreach ($oldActions as $a) {
                $stmt = $pdo->prepare(
                    'INSERT INTO project_actions (tenant_id, project_id, name, description, schedule_rule, starts_on, ends_on)
                     VALUES (:tenant_id, :project_id, :name, :description, :schedule_rule, NULL, NULL)'
                );
                $stmt->execute([
                    'tenant_id' => $tenantId,
                    'project_id' => $newProjectId,
                    'name' => (string)$a['name'],
                    'description' => $a['description'] ?? null,
                    'schedule_rule' => $a['schedule_rule'] ?? null,
                ]);
                $actionIdMap[(int)$a['id']] = (int)$pdo->lastInsertId();
            }

            $stmt = $pdo->prepare('SELECT * FROM project_tasks WHERE tenant_id = :tenant_id AND project_id = :project_id ORDER BY id ASC');
            $stmt->execute(['tenant_id' => $tenantId, 'project_id' => $projectId]);
            $oldTasks = $stmt->fetchAll();

            foreach ($oldTasks as $t) {
                $oldActionId = isset($t['action_id']) ? (int)$t['action_id'] : 0;
                $newActionId = $oldActionId > 0 && isset($actionIdMap[$oldActionId]) ? (int)$actionIdMap[$oldActionId] : null;

                $stmt = $pdo->prepare(
                    'INSERT INTO project_tasks (tenant_id, project_id, action_id, title, description, status, due_on, assigned_tier_id, assigned_user_id)
                     VALUES (:tenant_id, :project_id, :action_id, :title, :description, :status, NULL, NULL, NULL)'
                );
                $stmt->execute([
                    'tenant_id' => $tenantId,
                    'project_id' => $newProjectId,
                    'action_id' => $newActionId,
                    'title' => (string)$t['title'],
                    'description' => $t['description'] ?? null,
                    'status' => 'todo',
                ]);
            }

            $stmt = $pdo->prepare('SELECT * FROM project_participants WHERE tenant_id = :tenant_id AND project_id = :project_id ORDER BY id ASC');
            $stmt->execute(['tenant_id' => $tenantId, 'project_id' => $projectId]);
            $oldParticipants = $stmt->fetchAll();

            foreach ($oldParticipants as $pp) {
                $oldActionId = isset($pp['action_id']) ? (int)$pp['action_id'] : 0;
                $newActionId = $oldActionId > 0 && isset($actionIdMap[$oldActionId]) ? (int)$actionIdMap[$oldActionId] : null;

                $stmt = $pdo->prepare(
                    'INSERT INTO project_participants (tenant_id, project_id, action_id, tier_id, user_id, role, notes)
                     VALUES (:tenant_id, :project_id, :action_id, :tier_id, :user_id, :role, :notes)'
                );
                $stmt->execute([
                    'tenant_id' => $tenantId,
                    'project_id' => $newProjectId,
                    'action_id' => $newActionId,
                    'tier_id' => $pp['tier_id'] ?? null,
                    'user_id' => $pp['user_id'] ?? null,
                    'role' => (string)$pp['role'],
                    'notes' => $pp['notes'] ?? null,
                ]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        Session::flash('success', 'Projet dupliqué.');
        redirect(tenant_path('/projects/view?id=' . $newProjectId));
    }
}
