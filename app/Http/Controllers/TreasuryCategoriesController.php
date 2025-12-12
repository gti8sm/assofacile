<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Db;
use App\Support\Modules;
use App\Support\Session;

final class TreasuryCategoriesController
{
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

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT id, name, created_at FROM treasury_categories WHERE tenant_id = :tenant_id ORDER BY name ASC');
        $stmt->execute(['tenant_id' => (int)$_SESSION['tenant_id']]);
        $categories = $stmt->fetchAll();

        $flash = Session::flash('success');
        $error = Session::flash('error');
        require base_path('views/treasury/categories.php');
    }

    public static function store(): void
    {
        self::guard();

        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            Session::flash('error', 'Nom invalide.');
            redirect('/treasury/categories');
        }

        $pdo = Db::pdo();

        try {
            $stmt = $pdo->prepare('INSERT INTO treasury_categories (tenant_id, name) VALUES (:tenant_id, :name)');
            $stmt->execute([
                'tenant_id' => (int)$_SESSION['tenant_id'],
                'name' => $name,
            ]);
        } catch (\Throwable $e) {
            Session::flash('error', 'Catégorie déjà existante ou erreur.');
            redirect('/treasury/categories');
        }

        Session::flash('success', 'Catégorie créée.');
        redirect('/treasury/categories');
    }
}
