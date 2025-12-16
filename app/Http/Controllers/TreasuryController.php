<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Db;
use App\Support\Session;
use App\Support\Access;

final class TreasuryController
{
    private static function guard(): void
    {
        Access::require('treasury', 'read');
    }

    public static function index(): void
    {
        self::guard();

        $period = (string)($_GET['period'] ?? 'month');
        $from = '';
        $to = '';

        $q = trim((string)($_GET['q'] ?? ''));
        $typeFilter = (string)($_GET['type'] ?? '');
        $categoryIdFilter = (string)($_GET['category_id'] ?? '');

        $today = new \DateTimeImmutable('today');
        if ($period === 'prev_month') {
            $start = $today->modify('first day of last month');
            $end = $today->modify('last day of last month');
            $from = $start->format('Y-m-d');
            $to = $end->format('Y-m-d');
        } elseif ($period === 'year') {
            $start = $today->setDate((int)$today->format('Y'), 1, 1);
            $end = $today->setDate((int)$today->format('Y'), 12, 31);
            $from = $start->format('Y-m-d');
            $to = $end->format('Y-m-d');
        } elseif ($period === 'custom') {
            $from = (string)($_GET['from'] ?? '');
            $to = (string)($_GET['to'] ?? '');
            $isValid = (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) && (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $to);
            if (!$isValid) {
                $period = 'month';
            }
        }

        if ($period === 'month') {
            $start = $today->modify('first day of this month');
            $end = $today->modify('last day of this month');
            $from = $start->format('Y-m-d');
            $to = $end->format('Y-m-d');
        }

        if (!in_array($typeFilter, ['', 'expense', 'income'], true)) {
            $typeFilter = '';
        }

        $categoryIdInt = null;
        if ($categoryIdFilter !== '') {
            $tmp = (int)$categoryIdFilter;
            if ($tmp > 0) {
                $categoryIdInt = $tmp;
            }
        }

        $pdo = Db::pdo();

        $stmt = $pdo->prepare('SELECT id, name FROM treasury_categories WHERE tenant_id = :tenant_id ORDER BY name ASC');
        $stmt->execute(['tenant_id' => (int)$_SESSION['tenant_id']]);
        $categories = $stmt->fetchAll();

        if ($categoryIdInt !== null) {
            $exists = false;
            foreach ($categories as $c) {
                if ((int)($c['id'] ?? 0) === $categoryIdInt) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $categoryIdInt = null;
                $categoryIdFilter = '';
            }
        }

        $where = [
            'tt.tenant_id = :tenant_id',
            'tt.occurred_on >= :from',
            'tt.occurred_on <= :to',
        ];
        $params = [
            'tenant_id' => (int)$_SESSION['tenant_id'],
            'from' => $from,
            'to' => $to,
        ];

        if ($q !== '') {
            $where[] = 'tt.label LIKE :q';
            $params['q'] = '%' . $q . '%';
        }

        if ($typeFilter !== '') {
            $where[] = 'tt.type = :type';
            $params['type'] = $typeFilter;
        }

        if ($categoryIdInt !== null) {
            $where[] = 'tt.category_id = :category_id';
            $params['category_id'] = $categoryIdInt;
        }

        $stmt = $pdo->prepare(
            'SELECT tt.id, tt.type, tt.amount_cents, tt.label, tt.occurred_on, tt.is_cleared, tc.name AS category_name
             FROM treasury_transactions tt
             LEFT JOIN treasury_categories tc ON tc.id = tt.category_id
             WHERE ' . implode("\n               AND ", $where) . '
             ORDER BY tt.occurred_on DESC, tt.id DESC
             LIMIT 100'
        );
        $stmt->execute($params);
        $transactions = $stmt->fetchAll();

        $totalExpenseCents = 0;
        $totalIncomeCents = 0;
        foreach ($transactions as $t) {
            $amount = (int)($t['amount_cents'] ?? 0);
            if ((string)($t['type'] ?? '') === 'income') {
                $totalIncomeCents += $amount;
            } else {
                $totalExpenseCents += $amount;
            }
        }
        $balanceCents = $totalIncomeCents - $totalExpenseCents;

        $flash = Session::flash('success');
        require base_path('views/treasury/index.php');
    }

    public static function create(): void
    {
        Access::require('treasury', 'read');

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT id, name FROM treasury_categories WHERE tenant_id = :tenant_id ORDER BY name ASC');
        $stmt->execute(['tenant_id' => (int)$_SESSION['tenant_id']]);
        $categories = $stmt->fetchAll();

        $prefill = [];
        $duplicateId = (int)($_GET['duplicate_id'] ?? 0);
        if ($duplicateId > 0) {
            $stmt = $pdo->prepare('SELECT type, amount_cents, label, category_id FROM treasury_transactions WHERE id = :id AND tenant_id = :tenant_id');
            $stmt->execute([
                'id' => $duplicateId,
                'tenant_id' => (int)$_SESSION['tenant_id'],
            ]);
            $src = $stmt->fetch();
            if ($src) {
                $prefill = [
                    'type' => (string)$src['type'],
                    'label' => (string)$src['label'],
                    'amount' => number_format(((int)$src['amount_cents']) / 100, 2, ',', ''),
                    'category_id' => $src['category_id'] !== null ? (string)$src['category_id'] : '',
                    'occurred_on' => date('Y-m-d'),
                ];
            }
        }

        $error = Session::flash('error');
        require base_path('views/treasury/new.php');
    }

    public static function toggleCleared(): void
    {
        Access::require('treasury', 'write');

        $id = (int)($_POST['id'] ?? 0);
        $returnTo = (string)($_POST['return_to'] ?? '/treasury');
        if ($id <= 0) {
            Session::flash('error', 'Transaction invalide.');
            redirect('/treasury');
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT is_cleared FROM treasury_transactions WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute([
            'id' => $id,
            'tenant_id' => (int)$_SESSION['tenant_id'],
        ]);
        $row = $stmt->fetch();
        if (!$row) {
            Session::flash('error', 'Transaction introuvable.');
            redirect('/treasury');
        }

        $isCleared = ((int)($row['is_cleared'] ?? 0) === 1);
        $newCleared = $isCleared ? 0 : 1;

        $stmt = $pdo->prepare('UPDATE treasury_transactions SET is_cleared = :is_cleared, cleared_at = :cleared_at WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute([
            'is_cleared' => $newCleared,
            'cleared_at' => $newCleared === 1 ? date('Y-m-d H:i:s') : null,
            'id' => $id,
            'tenant_id' => (int)$_SESSION['tenant_id'],
        ]);

        redirect(str_starts_with($returnTo, '/') ? $returnTo : '/treasury');
    }

    public static function store(): void
    {
        Access::require('treasury', 'write');

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

        $transactionId = (int)$pdo->lastInsertId();

        $storeDriver = (string)($_POST['store_driver'] ?? 'local');
        $preferDrive = ($storeDriver === 'gdrive');
        $saved = TreasuryAttachmentsController::saveUploadedFiles((int)$_SESSION['tenant_id'], $transactionId, $_FILES['attachments'] ?? null, $preferDrive);
        if ($saved > 0) {
            Session::flash('success', 'Transaction enregistrée + ' . $saved . ' justificatif(s).');
            redirect('/treasury/attachments?transaction_id=' . $transactionId);
        }

        Session::flash('success', 'Transaction enregistrée.');
        redirect('/treasury');
    }

    public static function exportCsv(): void
    {
        Access::require('treasury', 'read');

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
