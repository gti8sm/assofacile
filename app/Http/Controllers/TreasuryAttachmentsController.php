<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Db;
use App\Support\Modules;
use App\Support\Session;
use App\Support\Storage;

final class TreasuryAttachmentsController
{
    private const MAX_FILE_SIZE = 10485760; // 10 MB

    private static function guard(): void
    {
        if (!isset($_SESSION['user_id'], $_SESSION['tenant_id'])) {
            redirect('/login');
        }

        if (!Modules::isEnabled((int)$_SESSION['tenant_id'], 'treasury')) {
            http_response_code(403);
            echo '403';
            exit;
        }
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
        self::guard();

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

        $saved = self::saveUploadedFiles((int)$_SESSION['tenant_id'], $transactionId, $_FILES['attachments'] ?? null);

        if ($saved <= 0) {
            Session::flash('error', 'Aucun fichier valide (jpg/png/pdf, max 10 Mo).');
        } else {
            Session::flash('success', $saved . ' fichier(s) ajouté(s).');
        }

        redirect('/treasury/attachments?transaction_id=' . $transactionId);
    }

    public static function saveUploadedFiles(int $tenantId, int $transactionId, mixed $files): int
    {
        if (!is_array($files) || !isset($files['name']) || !is_array($files['name'])) {
            return 0;
        }

        $pdo = Db::pdo();

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
        self::guard();

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
            http_response_code(501);
            echo '501';
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
