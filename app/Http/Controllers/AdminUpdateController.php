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

        $pdo = Db::pdo();
        $pendingFiles = Migrator::pending($pdo);
        $pending = array_map('basename', $pendingFiles);

        $flash = Session::flash('success');
        $error = Session::flash('error');

        require base_path('views/admin/update.php');
    }

    public static function run(): void
    {
        self::requireAdmin();

        $pdo = Db::pdo();
        $res = Migrator::applyPending($pdo);

        if (!$res['ok']) {
            Session::flash('error', 'Mise à jour échouée: ' . (string)$res['error']);
            redirect('/admin/update');
        }

        $count = count($res['applied']);
        Session::flash('success', $count > 0 ? ($count . ' migration(s) appliquée(s).') : 'Aucune mise à jour à appliquer.');
        redirect('/admin/update');
    }
}
