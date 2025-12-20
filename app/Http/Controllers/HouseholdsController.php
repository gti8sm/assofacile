<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Db;
use App\Support\Access;
use App\Support\Session;

final class HouseholdsController
{
    public static function index(): void
    {
        Access::require('members', 'read');

        $tenantId = (int)$_SESSION['tenant_id'];
        $pdo = Db::pdo();

        $stmt = $pdo->prepare(
            'SELECT h.id, h.name, h.address, h.created_at,
                    (SELECT COUNT(*) FROM members m WHERE m.tenant_id = h.tenant_id AND m.household_id = h.id) AS members_count
             FROM households h
             WHERE h.tenant_id = :tenant_id
             ORDER BY h.id DESC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        $households = $stmt->fetchAll();

        $flash = Session::flash('success');
        $error = Session::flash('error');
        require base_path('views/households/index.php');
    }

    public static function create(): void
    {
        Access::require('members', 'write');

        $error = Session::flash('error');
        require base_path('views/households/new.php');
    }

    public static function store(): void
    {
        Access::require('members', 'write');

        $tenantId = (int)$_SESSION['tenant_id'];
        $name = trim((string)($_POST['name'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));

        if ($name === '' && $address === '') {
            Session::flash('error', 'Nom ou adresse requis.');
            redirect('/households/new');
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('INSERT INTO households (tenant_id, name, address) VALUES (:tenant_id, :name, :address)');
        $stmt->execute([
            'tenant_id' => $tenantId,
            'name' => ($name !== '' ? $name : null),
            'address' => ($address !== '' ? $address : null),
        ]);

        Session::flash('success', 'Foyer créé.');
        redirect('/households');
    }

    public static function edit(): void
    {
        Access::require('members', 'write');

        $tenantId = (int)$_SESSION['tenant_id'];
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo '400';
            return;
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT * FROM households WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
        $stmt->execute([
            'id' => $id,
            'tenant_id' => $tenantId,
        ]);
        $household = $stmt->fetch();
        if (!$household) {
            http_response_code(404);
            echo '404';
            return;
        }

        $stmt = $pdo->prepare(
            'SELECT id, first_name, last_name, relationship
             FROM members
             WHERE tenant_id = :tenant_id AND household_id = :household_id
             ORDER BY relationship ASC, last_name ASC, first_name ASC, id DESC'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'household_id' => $id,
        ]);
        $members = $stmt->fetchAll();

        $membershipProducts = [];
        try {
            $stmt = $pdo->prepare(
                'SELECT id, label, amount_default_cents, period_months
                 FROM membership_products
                 WHERE tenant_id = :tenant_id AND is_active = 1 AND applies_to = \'household\'
                 ORDER BY id DESC'
            );
            $stmt->execute(['tenant_id' => $tenantId]);
            $membershipProducts = $stmt->fetchAll();
        } catch (\Throwable $e) {
            $membershipProducts = [];
        }

        $membershipSubscriptions = [];
        try {
            $stmt = $pdo->prepare(
                'SELECT ms.id, ms.amount_cents, ms.start_date, ms.end_date, ms.status, ms.payment_provider, mp.label AS product_label
                 FROM membership_subscriptions ms
                 LEFT JOIN membership_products mp ON mp.id = ms.product_id
                 WHERE ms.tenant_id = :tenant_id AND ms.household_id = :household_id
                 ORDER BY ms.start_date DESC, ms.id DESC'
            );
            $stmt->execute([
                'tenant_id' => $tenantId,
                'household_id' => $id,
            ]);
            $membershipSubscriptions = $stmt->fetchAll();
        } catch (\Throwable $e) {
            $membershipSubscriptions = [];
        }

        $flash = Session::flash('success');
        $error = Session::flash('error');
        require base_path('views/households/edit.php');
    }

    public static function update(): void
    {
        Access::require('members', 'write');

        $tenantId = (int)$_SESSION['tenant_id'];
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo '400';
            return;
        }

        $name = trim((string)($_POST['name'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));

        if ($name === '' && $address === '') {
            Session::flash('error', 'Nom ou adresse requis.');
            redirect('/households/edit?id=' . $id);
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare(
            'UPDATE households
             SET name = :name,
                 address = :address
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute([
            'name' => ($name !== '' ? $name : null),
            'address' => ($address !== '' ? $address : null),
            'id' => $id,
            'tenant_id' => $tenantId,
        ]);

        Session::flash('success', 'Foyer mis à jour.');
        redirect('/households/edit?id=' . $id);
    }
}
