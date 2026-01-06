<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Db;
use App\Support\Access;
use App\Support\Session;

final class TreasuryCategoriesController
{
    private static function guard(): void
    {
        Access::require('treasury', 'read');
    }

    public static function index(): void
    {
        self::guard();

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT id, name, account_code, created_at FROM treasury_categories WHERE tenant_id = :tenant_id ORDER BY name ASC');
        $stmt->execute(['tenant_id' => (int)$_SESSION['tenant_id']]);
        $categories = $stmt->fetchAll();

        $flash = Session::flash('success');
        $error = Session::flash('error');
        require base_path('views/treasury/categories.php');
    }

    public static function store(): void
    {
        Access::require('treasury', 'write');

        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            Session::flash('error', 'Nom invalide.');
            redirect(tenant_path('/treasury/categories'));
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
            redirect(tenant_path('/treasury/categories'));
        }

        Session::flash('success', 'Catégorie créée.');
        redirect(tenant_path('/treasury/categories'));
    }

    public static function update(): void
    {
        Access::require('treasury', 'write');

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            Session::flash('error', 'Catégorie invalide.');
            redirect(tenant_path('/treasury/categories'));
        }

        $accountCode = trim((string)($_POST['account_code'] ?? ''));
        if ($accountCode !== '') {
            if (mb_strlen($accountCode) > 32) {
                $accountCode = mb_substr($accountCode, 0, 32);
            }
            if (!preg_match('/^[0-9A-Za-z._\-]+$/', $accountCode)) {
                Session::flash('error', 'Compte comptable invalide.');
                redirect(tenant_path('/treasury/categories'));
            }
        }

        $pdo = Db::pdo();
        $tenantId = (int)$_SESSION['tenant_id'];

        $stmt = $pdo->prepare('UPDATE treasury_categories SET account_code = :account_code WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute([
            'account_code' => ($accountCode !== '' ? $accountCode : null),
            'id' => $id,
            'tenant_id' => $tenantId,
        ]);

        Session::flash('success', 'Catégorie mise à jour.');
        redirect(tenant_path('/treasury/categories'));
    }
}
