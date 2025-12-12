<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Db;
use App\Support\Session;
use App\Support\Modules;

final class TreasuryController
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
        $stmt = $pdo->prepare(
            'SELECT tt.id, tt.type, tt.amount_cents, tt.label, tt.occurred_on, tc.name AS category_name
             FROM treasury_transactions tt
             LEFT JOIN treasury_categories tc ON tc.id = tt.category_id
             WHERE tt.tenant_id = :tenant_id
             ORDER BY tt.occurred_on DESC, tt.id DESC
             LIMIT 100'
        );
        $stmt->execute(['tenant_id' => (int)$_SESSION['tenant_id']]);
        $transactions = $stmt->fetchAll();

        $flash = Session::flash('success');
        require base_path('views/treasury/index.php');
    }

    public static function create(): void
    {
        self::guard();

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT id, name FROM treasury_categories WHERE tenant_id = :tenant_id ORDER BY name ASC');
        $stmt->execute(['tenant_id' => (int)$_SESSION['tenant_id']]);
        $categories = $stmt->fetchAll();

        $error = Session::flash('error');
        require base_path('views/treasury/new.php');
    }

    public static function store(): void
    {
        self::guard();

        $type = (string)($_POST['type'] ?? 'expense');
        $label = trim((string)($_POST['label'] ?? ''));
        $amount = (string)($_POST['amount'] ?? '');
        $date = (string)($_POST['occurred_on'] ?? '');
        $categoryId = (string)($_POST['category_id'] ?? '');

        if (!in_array($type, ['expense', 'income'], true) || $label === '' || $amount === '' || $date === '') {
            Session::flash('error', 'Champs invalides.');
            redirect('/treasury/new');
        }

        $amountCents = (int)round(((float)str_replace(',', '.', $amount)) * 100);
        if ($amountCents <= 0) {
            Session::flash('error', 'Montant invalide.');
            redirect('/treasury/new');
        }

        $categoryIdInt = null;
        if ($categoryId !== '') {
            $categoryIdInt = (int)$categoryId;
            if ($categoryIdInt <= 0) {
                Session::flash('error', 'Catégorie invalide.');
                redirect('/treasury/new');
            }

            $pdo = Db::pdo();
            $stmt = $pdo->prepare('SELECT id FROM treasury_categories WHERE id = :id AND tenant_id = :tenant_id');
            $stmt->execute([
                'id' => $categoryIdInt,
                'tenant_id' => (int)$_SESSION['tenant_id'],
            ]);
            if (!$stmt->fetch()) {
                Session::flash('error', 'Catégorie invalide.');
                redirect('/treasury/new');
            }
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('INSERT INTO treasury_transactions (tenant_id, created_by_user_id, type, amount_cents, label, occurred_on, category_id) VALUES (:tenant_id, :user_id, :type, :amount_cents, :label, :occurred_on, :category_id)');
        $stmt->execute([
            'tenant_id' => (int)$_SESSION['tenant_id'],
            'user_id' => (int)$_SESSION['user_id'],
            'type' => $type,
            'amount_cents' => $amountCents,
            'label' => $label,
            'occurred_on' => $date,
            'category_id' => $categoryIdInt,
        ]);

        Session::flash('success', 'Transaction enregistrée.');
        redirect('/treasury');
    }

    public static function exportCsv(): void
    {
        self::guard();

        $pdo = Db::pdo();
        $stmt = $pdo->prepare(
            'SELECT tt.occurred_on, tt.type, tt.label, tt.amount_cents, tc.name AS category_name
             FROM treasury_transactions tt
             LEFT JOIN treasury_categories tc ON tc.id = tt.category_id
             WHERE tt.tenant_id = :tenant_id
             ORDER BY tt.occurred_on DESC, tt.id DESC'
        );
        $stmt->execute(['tenant_id' => (int)$_SESSION['tenant_id']]);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="treasury_export.csv"');

        $out = fopen('php://output', 'w');
        if ($out === false) {
            http_response_code(500);
            echo '500';
            return;
        }

        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['date', 'type', 'libelle', 'categorie', 'montant_eur'], ';');

        while ($row = $stmt->fetch()) {
            $amountEur = number_format(((int)$row['amount_cents']) / 100, 2, ',', '');
            fputcsv($out, [
                (string)$row['occurred_on'],
                (string)$row['type'],
                (string)$row['label'],
                (string)($row['category_name'] ?? ''),
                $amountEur,
            ], ';');
        }

        fclose($out);
    }
}
