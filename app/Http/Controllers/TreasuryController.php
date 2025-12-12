<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Db;
use App\Support\Session;

final class TreasuryController
{
    public static function index(): void
    {
        if (!isset($_SESSION['user_id'], $_SESSION['tenant_id'])) {
            redirect('/login');
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT id, type, amount_cents, label, occurred_on FROM treasury_transactions WHERE tenant_id = :tenant_id ORDER BY occurred_on DESC, id DESC LIMIT 100');
        $stmt->execute(['tenant_id' => (int)$_SESSION['tenant_id']]);
        $transactions = $stmt->fetchAll();

        $flash = Session::flash('success');
        require base_path('views/treasury/index.php');
    }

    public static function create(): void
    {
        if (!isset($_SESSION['user_id'], $_SESSION['tenant_id'])) {
            redirect('/login');
        }

        $error = Session::flash('error');
        require base_path('views/treasury/new.php');
    }

    public static function store(): void
    {
        if (!isset($_SESSION['user_id'], $_SESSION['tenant_id'])) {
            redirect('/login');
        }

        $type = (string)($_POST['type'] ?? 'expense');
        $label = trim((string)($_POST['label'] ?? ''));
        $amount = (string)($_POST['amount'] ?? '');
        $date = (string)($_POST['occurred_on'] ?? '');

        if (!in_array($type, ['expense', 'income'], true) || $label === '' || $amount === '' || $date === '') {
            Session::flash('error', 'Champs invalides.');
            redirect('/treasury/new');
        }

        $amountCents = (int)round(((float)str_replace(',', '.', $amount)) * 100);
        if ($amountCents <= 0) {
            Session::flash('error', 'Montant invalide.');
            redirect('/treasury/new');
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('INSERT INTO treasury_transactions (tenant_id, created_by_user_id, type, amount_cents, label, occurred_on) VALUES (:tenant_id, :user_id, :type, :amount_cents, :label, :occurred_on)');
        $stmt->execute([
            'tenant_id' => (int)$_SESSION['tenant_id'],
            'user_id' => (int)$_SESSION['user_id'],
            'type' => $type,
            'amount_cents' => $amountCents,
            'label' => $label,
            'occurred_on' => $date,
        ]);

        Session::flash('success', 'Transaction enregistrée.');
        redirect('/treasury');
    }
}
