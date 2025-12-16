<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Db;
use App\Support\Access;
use App\Support\Session;
use App\Support\Storage;
use App\Support\GoogleDrive;

final class TreasuryAttachmentsController
{
    private const MAX_FILE_SIZE = 10485760; // 10 MB

    private static function guard(): void
    {
        Access::require('treasury', 'read');
    }

    public static function index(): void
    {
        self::guard();

        $transactionId = (int)($_GET['transaction_id'] ?? 0);
        if ($transactionId <= 0) {
            http_response_code(400);
            echo '400';
            return;
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT id, type, label, occurred_on FROM treasury_transactions WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute([
            'id' => $transactionId,
            'tenant_id' => (int)$_SESSION['tenant_id'],
        ]);
        $transaction = $stmt->fetch();
        if (!$transaction) {
            http_response_code(404);
            echo '404';
            return;
        }

        $stmt = $pdo->prepare('SELECT id, original_name, mime_type, size_bytes, storage_driver, created_at FROM treasury_attachments WHERE tenant_id = :tenant_id AND transaction_id = :tx ORDER BY id DESC');
        $stmt->execute([
            'tenant_id' => (int)$_SESSION['tenant_id'],
            'tx' => $transactionId,
        ]);
        $attachments = $stmt->fetchAll();

        $flash = Session::flash('success');
        $error = Session::flash('error');

        require base_path('views/treasury/attachments.php');
    }

    public static function store(): void
    {
        Access::require('treasury', 'write');

        $transactionId = (int)($_POST['transaction_id'] ?? 0);
        if ($transactionId <= 0) {
            Session::flash('error', 'Transaction invalide.');
            redirect('/treasury');
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT id FROM treasury_transactions WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute([
            'id' => $transactionId,
            'tenant_id' => (int)$_SESSION['tenant_id'],
        ]);
        if (!$stmt->fetch()) {
            Session::flash('error', 'Transaction invalide.');
            redirect('/treasury');
        }

        $storeDriver = (string)($_POST['store_driver'] ?? 'local');
        $preferDrive = ($storeDriver === 'gdrive');
        $saved = self::saveUploadedFiles((int)$_SESSION['tenant_id'], $transactionId, $_FILES['attachments'] ?? null, $preferDrive);

        if ($saved <= 0) {
            Session::flash('error', 'Aucun fichier valide (jpg/png/pdf, max 10 Mo).');
        } else {
            Session::flash('success', $saved . ' fichier(s) ajouté(s).');
        }

        redirect('/treasury/attachments?transaction_id=' . $transactionId);
    }

    public static function saveUploadedFiles(int $tenantId, int $transactionId, mixed $files, bool $preferDrive = false): int
    {
        if (!is_array($files) || !isset($files['name']) || !is_array($files['name'])) {
            return 0;
        }

        $pdo = Db::pdo();

        $useDrive = $preferDrive
            && Modules::isEnabled($tenantId, 'drive')
            && GoogleDrive::isConfigured()
            && GoogleDrive::isAvailable()
            && GoogleDrive::isConnected($tenantId);

        $drive = $useDrive ? GoogleDrive::getService($tenantId) : null;
        $driveFolderId = $useDrive ? GoogleDrive::getDriveFolderId($tenantId) : null;

        $saved = 0;
        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            $error = (int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($error !== UPLOAD_ERR_OK) {
                continue;
            }

            $tmp = (string)($files['tmp_name'][$i] ?? '');
            $origName = (string)($files['name'][$i] ?? 'file');
            $size = (int)($files['size'][$i] ?? 0);

            if ($tmp === '' || !is_file($tmp) || $size <= 0 || $size > self::MAX_FILE_SIZE) {
                continue;
            }

            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = (string)$finfo->file($tmp);
            if (!in_array($mime, ['image/jpeg', 'image/png', 'application/pdf'], true)) {
                continue;
            }

            $ext = match ($mime) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'application/pdf' => 'pdf',
                default => 'bin',
            };

            if ($drive !== null) {
                try {
                    $meta = ['name' => basename($origName)];
                    if ($driveFolderId) {
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

                        $fileId = (string)($created->id ?? '');
                        if ($fileId !== '') {
                            $stmt = $pdo->prepare('INSERT INTO treasury_attachments (tenant_id, transaction_id, storage_driver, gdrive_file_id, original_name, mime_type, size_bytes) VALUES (:tenant_id, :tx, :driver, :file_id, :name, :mime, :size)');
                            $stmt->execute([
                                'tenant_id' => $tenantId,
                                'tx' => $transactionId,
                                'driver' => 'gdrive',
                                'file_id' => $fileId,
                                'name' => $origName,
                                'mime' => $mime,
                                'size' => $size,
                            ]);
                            $saved++;
                            continue;
                        }
                    }
                } catch (\Throwable $e) {
                    // fallback local
                }
            }

            $dir = Storage::privatePath('tenant_' . $tenantId . '/treasury/' . $transactionId);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }

            $filename = bin2hex(random_bytes(16)) . '.' . $ext;
            $dest = $dir . DIRECTORY_SEPARATOR . $filename;

            if (!move_uploaded_file($tmp, $dest)) {
                continue;
            }

            $rel = 'tenant_' . $tenantId . '/treasury/' . $transactionId . '/' . $filename;

            $stmt = $pdo->prepare('INSERT INTO treasury_attachments (tenant_id, transaction_id, storage_driver, local_path, original_name, mime_type, size_bytes) VALUES (:tenant_id, :tx, :driver, :path, :name, :mime, :size)');
            $stmt->execute([
                'tenant_id' => $tenantId,
                'tx' => $transactionId,
                'driver' => 'local',
                'path' => $rel,
                'name' => $origName,
                'mime' => $mime,
                'size' => $size,
            ]);

            $saved++;
        }

        return $saved;
    }

    public static function download(): void
    {
        Access::require('treasury', 'read');

        $attachmentId = (int)($_GET['id'] ?? 0);
        if ($attachmentId <= 0) {
            http_response_code(400);
            echo '400';
            return;
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT id, storage_driver, local_path, gdrive_file_id, original_name, mime_type, size_bytes FROM treasury_attachments WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
        $stmt->execute([
            'id' => $attachmentId,
            'tenant_id' => (int)$_SESSION['tenant_id'],
        ]);
        $att = $stmt->fetch();
        if (!$att) {
            http_response_code(404);
            echo '404';
            return;
        }

        if ((string)$att['storage_driver'] === 'gdrive') {
            $tenantId = (int)$_SESSION['tenant_id'];
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

            $fileId = (string)($att['gdrive_file_id'] ?? '');
            if ($fileId === '') {
                http_response_code(404);
                echo '404';
                return;
            }

            $response = $service->files->get($fileId, ['alt' => 'media']);
            $body = $response->getBody();

            header('Content-Type: ' . (string)$att['mime_type']);
            header('Content-Disposition: attachment; filename="' . str_replace('"', '', (string)$att['original_name']) . '"');

            while (!$body->eof()) {
                echo $body->read(8192);
            }
            return;
        }

        $path = Storage::privatePath((string)$att['local_path']);
        if (!is_file($path)) {
            http_response_code(404);
            echo '404';
            return;
        }

        header('Content-Type: ' . (string)$att['mime_type']);
        header('Content-Length: ' . (string)$att['size_bytes']);
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', (string)$att['original_name']) . '"');

        readfile($path);
    }
}
