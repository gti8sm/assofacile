<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Db;
use App\Support\Access;
use App\Support\Session;

final class MembershipProductsController
{
    public static function index(): void
    {
        Access::require('members', 'read');

        $tenantId = (int)$_SESSION['tenant_id'];
        $pdo = Db::pdo();

        $stmt = $pdo->prepare(
            'SELECT id, label, applies_to, amount_default_cents, period_months, is_active, created_at
             FROM membership_products
             WHERE tenant_id = :tenant_id
             ORDER BY is_active DESC, id DESC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        $products = $stmt->fetchAll();

        $flash = Session::flash('success');
        $error = Session::flash('error');
        require base_path('views/memberships/products.php');
    }

    public static function create(): void
    {
        Access::require('members', 'write');

        $error = Session::flash('error');
        require base_path('views/memberships/product_new.php');
    }

    public static function store(): void
    {
        Access::require('members', 'write');

        $tenantId = (int)$_SESSION['tenant_id'];

        $label = trim((string)($_POST['label'] ?? ''));
        $appliesTo = (string)($_POST['applies_to'] ?? 'person');
        $periodMonths = (int)($_POST['period_months'] ?? 12);
        $amountDefault = trim((string)($_POST['amount_default'] ?? ''));

        if ($label === '') {
            Session::flash('error', 'Libellé requis.');
            redirect('/memberships/products/new');
        }

        if (!in_array($appliesTo, ['person', 'household'], true)) {
            $appliesTo = 'person';
        }

        if ($periodMonths <= 0 || $periodMonths > 60) {
            $periodMonths = 12;
        }

        $amountCents = null;
        if ($amountDefault !== '') {
            $amountCents = self::parseMoneyToCents($amountDefault);
            if ($amountCents < 0) {
                Session::flash('error', 'Montant invalide.');
                redirect('/memberships/products/new');
            }
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO membership_products (tenant_id, label, applies_to, amount_default_cents, period_months, is_active)
             VALUES (:tenant_id, :label, :applies_to, :amount_default_cents, :period_months, 1)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'label' => $label,
            'applies_to' => $appliesTo,
            'amount_default_cents' => $amountCents,
            'period_months' => $periodMonths,
        ]);

        Session::flash('success', 'Cotisation créée.');
        redirect('/memberships/products');
    }

    private static function parseMoneyToCents(string $raw): int
    {
        $raw = trim($raw);
        $raw = str_replace(' ', '', $raw);
        $raw = str_replace(',', '.', $raw);
        if ($raw === '' || !preg_match('/^\d+(\.\d{1,2})?$/', $raw)) {
            return -1;
        }
        $parts = explode('.', $raw, 2);
        $euros = (int)$parts[0];
        $cents = 0;
        if (isset($parts[1])) {
            $frac = str_pad($parts[1], 2, '0');
            $cents = (int)substr($frac, 0, 2);
        }
        return $euros * 100 + $cents;
    }
}
