<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Db;

final class DashboardController
{
    public static function index(): void
    {
        if (!isset($_SESSION['user_id'], $_SESSION['tenant_id'])) {
            redirect('/login');
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT name FROM tenants WHERE id = :id');
        $stmt->execute(['id' => (int)$_SESSION['tenant_id']]);
        $tenant = $stmt->fetch();

        require base_path('views/dashboard/index.php');
    }
}
