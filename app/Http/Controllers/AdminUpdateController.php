<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Db;
use App\Support\Migrator;
use App\Support\Session;

final class AdminUpdateController
{
    private static function requireAdmin(): void
    {
        if (!isset($_SESSION['user_id'], $_SESSION['tenant_id'])) {
            redirect('/login');
        }

        if (!isset($_SESSION['is_admin']) || (int)$_SESSION['is_admin'] !== 1) {
            http_response_code(403);
            echo '403';
            exit;
        }
    }

    public static function index(): void
    {
        self::requireAdmin();

        $pending = [];
        $applied = [];
        try {
            $pdo = Db::pdo();
            $pendingFiles = Migrator::pending($pdo);
            $pending = array_map('basename', $pendingFiles);
            $applied = Migrator::appliedList($pdo);
        } catch (\Throwable $e) {
            Session::flash('error', 'Erreur migrations: ' . $e->getMessage());
        }

        $flash = Session::flash('success');
        $error = Session::flash('error');

        require base_path('views/admin/update.php');
    }

    public static function backup(): void
    {
        self::requireAdmin();

        $host = \App\Support\Env::get('DB_HOST', '127.0.0.1');
        $port = \App\Support\Env::get('DB_PORT', '3306');
        $name = \App\Support\Env::get('DB_NAME', 'assofacile');
        $user = \App\Support\Env::get('DB_USER', 'root');
        $pass = \App\Support\Env::get('DB_PASS', '');

        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'assofacile_backup_' . date('Ymd_His') . '.sql';
        $cmd = 'mysqldump --single-transaction --quick --routines --triggers --host=' . escapeshellarg($host)
            . ' --port=' . escapeshellarg($port)
            . ' --user=' . escapeshellarg($user)
            . ' --password=' . escapeshellarg($pass)
            . ' ' . escapeshellarg($name)
            . ' > ' . escapeshellarg($tmp) . ' 2>&1';

        $out = [];
        $exit = 1;
        @exec($cmd, $out, $exit);

        if ($exit !== 0 || !is_file($tmp) || filesize($tmp) === 0) {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
            Session::flash('error', 'Backup impossible (mysqldump). ' . implode("\n", $out));
            redirect('/admin/update');
        }

        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . basename($tmp) . '"');
        header('Content-Length: ' . (string)filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    public static function run(): void
    {
        self::requireAdmin();

        $pdo = Db::pdo();

        $backupBefore = isset($_POST['backup_before']) && (string)$_POST['backup_before'] === '1';
        if ($backupBefore) {
            $host = \App\Support\Env::get('DB_HOST', '127.0.0.1');
            $port = \App\Support\Env::get('DB_PORT', '3306');
            $name = \App\Support\Env::get('DB_NAME', 'assofacile');
            $user = \App\Support\Env::get('DB_USER', 'root');
            $pass = \App\Support\Env::get('DB_PASS', '');

            $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'assofacile_backup_' . date('Ymd_His') . '.sql';
            $cmd = 'mysqldump --single-transaction --quick --routines --triggers --host=' . escapeshellarg($host)
                . ' --port=' . escapeshellarg($port)
                . ' --user=' . escapeshellarg($user)
                . ' --password=' . escapeshellarg($pass)
                . ' ' . escapeshellarg($name)
                . ' > ' . escapeshellarg($tmp) . ' 2>&1';

            $out = [];
            $exit = 1;
            @exec($cmd, $out, $exit);

            if ($exit !== 0 || !is_file($tmp) || filesize($tmp) === 0) {
                if (is_file($tmp)) {
                    @unlink($tmp);
                }
                Session::flash('error', 'Mise à jour annulée: backup impossible (mysqldump). ' . implode("\n", $out));
                redirect('/admin/update');
            }

            $_SESSION['_flash']['backup_file'] = basename($tmp);
            $_SESSION['_flash']['backup_path'] = $tmp;
        }

        $res = Migrator::applyPending($pdo);

        if (!$res['ok']) {
            Session::flash('error', 'Mise à jour échouée: ' . (string)$res['error']);
            redirect('/admin/update');
        }

        $count = count($res['applied']);
        $backupFile = Session::flash('backup_file');
        $backupPath = Session::flash('backup_path');
        if ($backupPath !== null && is_file($backupPath)) {
            @unlink($backupPath);
        }
        $msg = $count > 0 ? ($count . ' migration(s) appliquée(s).') : 'Aucune mise à jour à appliquer.';
        if (!empty($backupFile)) {
            $msg .= ' Backup créé: ' . $backupFile;
        }
        Session::flash('success', $msg);
        redirect('/admin/update');
    }
}
