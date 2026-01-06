<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Db;
use App\Support\ModuleSettings;
use App\Support\Session;
use App\Support\Access;
use App\Support\Storage;
use App\Support\GoogleDrive;
use App\Support\Modules;

final class TreasuryController
{
    private static function lastFiscalYearPeriod(int $tenantId, \DateTimeImmutable $today): array
    {
        $fiscalMonthRaw = ModuleSettings::getString($tenantId, 'treasury', 'fiscal_year_start_month', '1');
        $fiscalStartMonth = (int)$fiscalMonthRaw;
        if ($fiscalStartMonth < 1 || $fiscalStartMonth > 12) {
            $fiscalStartMonth = 1;
        }

        $currentYear = (int)$today->format('Y');
        $currentMonth = (int)$today->format('m');
        $fiscalStartYear = $currentMonth >= $fiscalStartMonth ? $currentYear : ($currentYear - 1);
        $prevFiscalStart = sprintf('%04d-%02d-01', $fiscalStartYear - 1, $fiscalStartMonth);
        $prevFiscalEndDt = (new \DateTimeImmutable($prevFiscalStart))->modify('+1 year')->modify('-1 day');
        return [$prevFiscalStart, $prevFiscalEndDt->format('Y-m-d')];
    }

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

    private static function isDateClosed(int $tenantId, string $dateYmd): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateYmd)) {
            return false;
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare(
            'SELECT 1
             FROM treasury_closures
             WHERE tenant_id = :tenant_id
               AND start_date <= :d
               AND end_date >= :d
             LIMIT 1'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'd' => $dateYmd]);
        return (bool)$stmt->fetch();
    }

    private static function requireNotClosed(int $tenantId, string $dateYmd, string $message = 'Période clôturée.'): void
    {
        if (self::isDateClosed($tenantId, $dateYmd)) {
            Session::flash('error', $message);
            redirect('/treasury');
        }
    }

    private static function isPeriodCoveredByClosure(int $tenantId, string $startYmd, string $endYmd): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startYmd) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endYmd) || $startYmd > $endYmd) {
            return false;
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare(
            'SELECT 1
             FROM treasury_closures
             WHERE tenant_id = :tenant_id
               AND start_date <= :start
               AND end_date >= :end
             LIMIT 1'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'start' => $startYmd, 'end' => $endYmd]);
        return (bool)$stmt->fetch();
    }

    private static function canSoftDeleteTreasury(): bool
    {
        if (!isset($_SESSION['tenant_id'], $_SESSION['user_id'])) {
            return false;
        }

        if (isset($_SESSION['is_admin']) && (int)$_SESSION['is_admin'] === 1) {
            return true;
        }

        $role = (string)($_SESSION['role'] ?? '');
        if (!in_array($role, ['manager', 'treasurer'], true)) {
            return false;
        }

        return Access::can((int)$_SESSION['tenant_id'], (int)$_SESSION['user_id'], 'treasury', 'write');
    }

    private static function guard(): void
    {
        Access::require('treasury', 'read');
    }

    private static function normalizeTierName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        $name = mb_strtolower($name);
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;
        $name = trim($name);
        if (mb_strlen($name) > 190) {
            $name = mb_substr($name, 0, 190);
        }

        return $name;
    }

    private static function findOrCreateFreeTierId(int $tenantId, string $name): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        if (mb_strlen($name) > 190) {
            $name = mb_substr($name, 0, 190);
        }

        $normalized = self::normalizeTierName($name);
        if ($normalized === '') {
            return null;
        }

        $pdo = Db::pdo();

        $stmt = $pdo->prepare('SELECT id FROM tiers WHERE tenant_id = :tenant_id AND normalized_name = :n LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId, 'n' => $normalized]);
        $row = $stmt->fetch();
        $existingId = (int)($row['id'] ?? 0);
        if ($existingId > 0) {
            return $existingId;
        }

        try {
            $stmt = $pdo->prepare('INSERT INTO tiers (tenant_id, member_id, name, normalized_name) VALUES (:tenant_id, NULL, :name, :normalized_name)');
            $stmt->execute([
                'tenant_id' => $tenantId,
                'name' => $name,
                'normalized_name' => $normalized,
            ]);
            $newId = (int)$pdo->lastInsertId();
            if ($newId > 0) {
                return $newId;
            }
        } catch (\Throwable $e) {
        }

        $stmt = $pdo->prepare('SELECT id FROM tiers WHERE tenant_id = :tenant_id AND normalized_name = :n LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId, 'n' => $normalized]);
        $row = $stmt->fetch();
        $existingId = (int)($row['id'] ?? 0);
        if ($existingId > 0) {
            return $existingId;
        }

        return null;
    }

    private static function analyticsEnabled(int $tenantId): bool
    {
        return ModuleSettings::getBool($tenantId, 'treasury', 'analytics_enabled', false);
    }

    private static function budgetAllocationsEnabled(int $tenantId): bool
    {
        return ModuleSettings::getBool($tenantId, 'treasury', 'budget_allocation_enabled', false);
    }

    public static function index(): void
    {
        self::guard();

        $tenantId = (int)($_SESSION['tenant_id'] ?? 0);

        $period = (string)($_GET['period'] ?? 'month');
        $from = '';
        $to = '';

        $q = trim((string)($_GET['q'] ?? ''));
        $typeFilter = (string)($_GET['type'] ?? '');
        $categoryIdFilter = (string)($_GET['category_id'] ?? '');
        $clearedFilter = (string)($_GET['cleared'] ?? '');

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

        if (!in_array($clearedFilter, ['', '0', '1'], true)) {
            $clearedFilter = '';
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
            'tt.deleted_at IS NULL',
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

        if ($clearedFilter !== '') {
            $where[] = 'tt.is_cleared = :is_cleared';
            $params['is_cleared'] = (int)$clearedFilter;
        }

        $stmt = $pdo->prepare(
            'SELECT tt.id, tt.type, tt.amount_cents, tt.label, tt.occurred_on, tt.is_cleared, tc.name AS category_name,
                    a.allocated_cents
             FROM treasury_transactions tt
             LEFT JOIN treasury_categories tc ON tc.id = tt.category_id
             LEFT JOIN (
                SELECT tenant_id, transaction_id, SUM(amount_cents) AS allocated_cents
                FROM treasury_transaction_allocations
                GROUP BY tenant_id, transaction_id
             ) a
               ON a.tenant_id = tt.tenant_id AND a.transaction_id = tt.id
             WHERE ' . implode("\n               AND ", $where) . '
             ORDER BY tt.occurred_on DESC, tt.id DESC
             LIMIT 100'
        );
        $stmt->execute($params);
        $transactions = $stmt->fetchAll();

        $stmt = $pdo->prepare(
            'SELECT start_date, end_date
             FROM treasury_closures
             WHERE tenant_id = :tenant_id
               AND start_date <= :to
               AND end_date >= :from
             ORDER BY start_date ASC'
        );
        $stmt->execute([
            'tenant_id' => (int)$_SESSION['tenant_id'],
            'from' => $from,
            'to' => $to,
        ]);
        $closures = $stmt->fetchAll();

        $isClosedDate = static function (string $dateYmd) use ($closures): bool {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateYmd)) {
                return false;
            }
            foreach ($closures as $c) {
                $start = (string)($c['start_date'] ?? '');
                $end = (string)($c['end_date'] ?? '');
                if ($start !== '' && $end !== '' && $start <= $dateYmd && $end >= $dateYmd) {
                    return true;
                }
            }
            return false;
        };

        foreach ($transactions as $i => $t) {
            $occurredOn = (string)($t['occurred_on'] ?? '');
            $transactions[$i]['is_closed'] = $isClosedDate($occurredOn) ? 1 : 0;
            $amountCents = (int)($t['amount_cents'] ?? 0);
            $allocatedCents = (int)($t['allocated_cents'] ?? 0);
            $transactions[$i]['is_allocated'] = ($amountCents !== 0 && $allocatedCents === $amountCents) ? 1 : 0;
        }

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS c
             FROM treasury_transactions tt
             WHERE tt.tenant_id = :tenant_id
               AND tt.deleted_at IS NULL
               AND tt.is_cleared = 0
               AND NOT EXISTS (
                   SELECT 1
                   FROM treasury_closures tc
                   WHERE tc.tenant_id = tt.tenant_id
                     AND tc.start_date <= tt.occurred_on
                     AND tc.end_date >= tt.occurred_on
               )'
        );
        $stmt->execute(['tenant_id' => (int)$_SESSION['tenant_id']]);
        $row = $stmt->fetch();
        $unreconciledCount = (int)($row['c'] ?? 0);

        $unallocatedCount = null;
        if (self::analyticsEnabled((int)$_SESSION['tenant_id'])) {
            try {
                $stmt = $pdo->prepare(
                    'SELECT COUNT(*) AS c
                     FROM treasury_transactions tt
                     LEFT JOIN (
                        SELECT tenant_id, transaction_id, SUM(amount_cents) AS allocated_cents
                        FROM treasury_transaction_allocations
                        WHERE tenant_id = :tenant_id
                        GROUP BY tenant_id, transaction_id
                     ) a
                       ON a.tenant_id = tt.tenant_id AND a.transaction_id = tt.id
                     WHERE tt.tenant_id = :tenant_id
                       AND tt.deleted_at IS NULL
                       AND tt.occurred_on >= :from
                       AND tt.occurred_on <= :to
                       AND (a.allocated_cents IS NULL OR a.allocated_cents <> tt.amount_cents)'
                );
                $stmt->execute([
                    'tenant_id' => (int)$_SESSION['tenant_id'],
                    'from' => $from,
                    'to' => $to,
                ]);
                $row = $stmt->fetch();
                $unallocatedCount = (int)($row['c'] ?? 0);
            } catch (\Throwable $e) {
                $unallocatedCount = 0;
            }
        }

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

        $closureSuggestions = [];
        if (!empty($_SESSION['is_admin'])) {
            try {
                $tenantId = (int)$_SESSION['tenant_id'];
                $today = new \DateTimeImmutable('today');

                $lastMonthStart = $today->modify('first day of last month')->format('Y-m-d');
                $lastMonthEnd = $today->modify('last day of last month')->format('Y-m-d');
                if (!self::isPeriodCoveredByClosure($tenantId, $lastMonthStart, $lastMonthEnd)) {
                    $closureSuggestions[] = [
                        'label' => 'Clôturer le mois dernier',
                        'start' => $lastMonthStart,
                        'end' => $lastMonthEnd,
                    ];
                }

                $y = (int)$today->format('Y') - 1;
                $lastYearStart = sprintf('%04d-01-01', $y);
                $lastYearEnd = sprintf('%04d-12-31', $y);
                if (!self::isPeriodCoveredByClosure($tenantId, $lastYearStart, $lastYearEnd)) {
                    $closureSuggestions[] = [
                        'label' => 'Clôturer l\'année dernière',
                        'start' => $lastYearStart,
                        'end' => $lastYearEnd,
                    ];
                }

                $fiscalMonthRaw = ModuleSettings::getString($tenantId, 'treasury', 'fiscal_year_start_month', '1');
                $fiscalStartMonth = (int)$fiscalMonthRaw;
                if ($fiscalStartMonth < 1 || $fiscalStartMonth > 12) {
                    $fiscalStartMonth = 1;
                }
                $currentYear = (int)$today->format('Y');
                $currentMonth = (int)$today->format('m');
                $fiscalStartYear = $currentMonth >= $fiscalStartMonth ? $currentYear : ($currentYear - 1);
                $prevFiscalStart = sprintf('%04d-%02d-01', $fiscalStartYear - 1, $fiscalStartMonth);
                $prevFiscalEndDt = (new \DateTimeImmutable($prevFiscalStart))->modify('+1 year')->modify('-1 day');
                $prevFiscalEnd = $prevFiscalEndDt->format('Y-m-d');
                if (!self::isPeriodCoveredByClosure($tenantId, $prevFiscalStart, $prevFiscalEnd)) {
                    $closureSuggestions[] = [
                        'label' => 'Clôturer l\'exercice précédent',
                        'start' => $prevFiscalStart,
                        'end' => $prevFiscalEnd,
                    ];
                }
            } catch (\Throwable $e) {
                $closureSuggestions = [];
            }
        }

        $flash = Session::flash('success');
        $budgetsEnabled = self::budgetAllocationsEnabled($tenantId);
        require base_path('views/treasury/index.php');
    }

    public static function analytics(): void
    {
        self::guard();

        $tenantId = (int)$_SESSION['tenant_id'];
        if (!self::analyticsEnabled($tenantId)) {
            Session::flash('error', 'Analytique désactivée (Admin → Modules → Trésorerie).');
            redirect('/admin/modules/settings?module=treasury');
        }

        $period = (string)($_GET['period'] ?? 'month');
        $from = '';
        $to = '';

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

        $pdo = Db::pdo();
        $stmt = $pdo->prepare(
            'SELECT ax.label AS axis_label, av.label AS value_label,
                    SUM(CASE WHEN tt.type = "income" THEN a.amount_cents ELSE 0 END) AS income_cents,
                    SUM(CASE WHEN tt.type = "expense" THEN a.amount_cents ELSE 0 END) AS expense_cents
             FROM treasury_transaction_allocations a
             JOIN treasury_transactions tt
               ON tt.id = a.transaction_id AND tt.tenant_id = a.tenant_id
             JOIN treasury_analytics_axes ax
               ON ax.id = a.axis_id AND ax.tenant_id = a.tenant_id
             JOIN treasury_analytics_values av
               ON av.id = a.value_id AND av.tenant_id = a.tenant_id
             WHERE a.tenant_id = :tenant_id
               AND tt.deleted_at IS NULL
               AND tt.occurred_on >= :from
               AND tt.occurred_on <= :to
             GROUP BY ax.label, av.label
             ORDER BY ax.label ASC, av.label ASC'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'from' => $from,
            'to' => $to,
        ]);
        $rows = $stmt->fetchAll();

        $unallocatedCount = 0;
        try {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) AS c
                 FROM treasury_transactions tt
                 LEFT JOIN (
                    SELECT tenant_id, transaction_id, SUM(amount_cents) AS allocated_cents
                    FROM treasury_transaction_allocations
                    WHERE tenant_id = :tenant_id
                    GROUP BY tenant_id, transaction_id
                 ) a
                   ON a.tenant_id = tt.tenant_id AND a.transaction_id = tt.id
                 WHERE tt.tenant_id = :tenant_id
                   AND tt.deleted_at IS NULL
                   AND tt.occurred_on >= :from
                   AND tt.occurred_on <= :to
                   AND (a.allocated_cents IS NULL OR a.allocated_cents <> tt.amount_cents)'
            );
            $stmt->execute([
                'tenant_id' => $tenantId,
                'from' => $from,
                'to' => $to,
            ]);
            $row = $stmt->fetch();
            $unallocatedCount = (int)($row['c'] ?? 0);
        } catch (\Throwable $e) {
            $unallocatedCount = 0;
        }

        require base_path('views/treasury/analytics.php');
    }

    public static function reconcile(): void
    {
        self::guard();

        $pdo = Db::pdo();
        $tenantId = (int)$_SESSION['tenant_id'];

        $stmt = $pdo->prepare('SELECT id, name FROM treasury_categories WHERE tenant_id = :tenant_id ORDER BY name ASC');
        $stmt->execute(['tenant_id' => $tenantId]);
        $categories = $stmt->fetchAll();

        $q = trim((string)($_GET['q'] ?? ''));
        $typeFilter = (string)($_GET['type'] ?? '');
        if (!in_array($typeFilter, ['', 'expense', 'income'], true)) {
            $typeFilter = '';
        }

        $where = [
            'tt.tenant_id = :tenant_id',
            'tt.deleted_at IS NULL',
            'tt.is_cleared = 0',
            'NOT EXISTS (SELECT 1 FROM treasury_closures tc WHERE tc.tenant_id = tt.tenant_id AND tc.start_date <= tt.occurred_on AND tc.end_date >= tt.occurred_on)',
        ];
        $params = [
            'tenant_id' => $tenantId,
        ];

        if ($q !== '') {
            $where[] = 'tt.label LIKE :q';
            $params['q'] = '%' . $q . '%';
        }
        if ($typeFilter !== '') {
            $where[] = 'tt.type = :type';
            $params['type'] = $typeFilter;
        }

        $stmt = $pdo->prepare(
            'SELECT tt.id, tt.type, tt.amount_cents, tt.label, tt.occurred_on, tt.category_id,
                    tt.payment_method, tt.counterparty, tt.reference,
                    tc.name AS category_name
             FROM treasury_transactions tt
             LEFT JOIN treasury_categories tc ON tc.id = tt.category_id
             WHERE ' . implode("\n               AND ", $where) . '
             ORDER BY tt.occurred_on DESC, tt.id DESC
             LIMIT 200'
        );
        $stmt->execute($params);
        $transactions = $stmt->fetchAll();

        $minDate = null;
        $maxDate = null;
        foreach ($transactions as $t) {
            $d = (string)($t['occurred_on'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                continue;
            }
            if ($minDate === null || $d < $minDate) {
                $minDate = $d;
            }
            if ($maxDate === null || $d > $maxDate) {
                $maxDate = $d;
            }
        }

        $closures = [];
        if ($minDate !== null && $maxDate !== null) {
            $stmt = $pdo->prepare(
                'SELECT start_date, end_date
                 FROM treasury_closures
                 WHERE tenant_id = :tenant_id
                   AND start_date <= :to
                   AND end_date >= :from
                 ORDER BY start_date ASC'
            );
            $stmt->execute([
                'tenant_id' => $tenantId,
                'from' => $minDate,
                'to' => $maxDate,
            ]);
            $closures = $stmt->fetchAll();
        }

        $isClosedDate = static function (string $dateYmd) use ($closures): bool {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateYmd)) {
                return false;
            }
            foreach ($closures as $c) {
                $start = (string)($c['start_date'] ?? '');
                $end = (string)($c['end_date'] ?? '');
                if ($start !== '' && $end !== '' && $start <= $dateYmd && $end >= $dateYmd) {
                    return true;
                }
            }
            return false;
        };

        foreach ($transactions as $i => $t) {
            $occurredOn = (string)($t['occurred_on'] ?? '');
            $transactions[$i]['is_closed'] = $isClosedDate($occurredOn) ? 1 : 0;
        }

        $flash = Session::flash('success');
        $error = Session::flash('error');
        require base_path('views/treasury/reconcile.php');
    }

    public static function updateReconcile(): void
    {
        Access::require('treasury', 'write');

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo '400';
            return;
        }

        $returnTo = (string)($_POST['return_to'] ?? '/treasury/reconcile');
        if (!str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//')) {
            $returnTo = '/treasury/reconcile';
        }

        $paymentMethod = trim((string)($_POST['payment_method'] ?? ''));
        $counterparty = trim((string)($_POST['counterparty'] ?? ''));
        $reference = trim((string)($_POST['reference'] ?? ''));
        $categoryId = (string)($_POST['category_id'] ?? '');
        $isCleared = (int)($_POST['is_cleared'] ?? 0) === 1 ? 1 : 0;

        if (!in_array($paymentMethod, ['', 'cash', 'card', 'transfer', 'check', 'other'], true)) {
            $paymentMethod = '';
        }

        $categoryIdInt = null;
        if ($categoryId !== '') {
            $tmp = (int)$categoryId;
            if ($tmp > 0) {
                $categoryIdInt = $tmp;
            }
        }

        $pdo = Db::pdo();
        $tenantId = (int)$_SESSION['tenant_id'];

        $stmt = $pdo->prepare('SELECT occurred_on FROM treasury_transactions WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $current = $stmt->fetch();
        if (!$current) {
            Session::flash('error', 'Transaction introuvable.');
            redirect($returnTo);
        }
        self::requireNotClosed($tenantId, (string)($current['occurred_on'] ?? ''), 'Période clôturée : rapprochement interdit.');

        if ($categoryIdInt !== null) {
            $stmt = $pdo->prepare('SELECT id FROM treasury_categories WHERE id = :id AND tenant_id = :tenant_id');
            $stmt->execute(['id' => $categoryIdInt, 'tenant_id' => $tenantId]);
            if (!$stmt->fetch()) {
                Session::flash('error', 'Catégorie invalide.');
                redirect($returnTo);
            }
        }

        $stmt = $pdo->prepare(
            'UPDATE treasury_transactions
             SET payment_method = :payment_method,
                 counterparty = :counterparty,
                 reference = :reference,
                 category_id = :category_id,
                 is_cleared = :is_cleared,
                 cleared_at = :cleared_at,
                 cleared_by_user_id = :cleared_by_user_id
             WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL'
        );
        $stmt->execute([
            'payment_method' => ($paymentMethod !== '' ? $paymentMethod : null),
            'counterparty' => ($counterparty !== '' ? $counterparty : null),
            'reference' => ($reference !== '' ? $reference : null),
            'category_id' => $categoryIdInt,
            'is_cleared' => $isCleared,
            'cleared_at' => $isCleared === 1 ? date('Y-m-d H:i:s') : null,
            'cleared_by_user_id' => $isCleared === 1 ? (int)$_SESSION['user_id'] : null,
            'id' => $id,
            'tenant_id' => $tenantId,
        ]);

        Session::flash('success', 'Rapprochement mis à jour.');
        redirect($returnTo);
    }

    public static function edit(): void
    {
        Access::require('treasury', 'read');

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo '400';
            return;
        }

        $pdo = Db::pdo();
        $tenantId = (int)$_SESSION['tenant_id'];

        $stmt = $pdo->prepare('SELECT id, name FROM treasury_categories WHERE tenant_id = :tenant_id ORDER BY name ASC');
        $stmt->execute(['tenant_id' => $tenantId]);
        $categories = $stmt->fetchAll();

        $stmt = $pdo->prepare(
            'SELECT tt.id, tt.type, tt.amount_cents, tt.label, tt.occurred_on, tt.category_id, tt.is_cleared,
                    tt.payment_method, tt.counterparty, tt.reference, tt.tier_id,
                    tr.name AS tier_name
             FROM treasury_transactions tt
             LEFT JOIN tiers tr
               ON tr.id = tt.tier_id AND tr.tenant_id = tt.tenant_id
             WHERE tt.id = :id AND tt.tenant_id = :tenant_id AND tt.deleted_at IS NULL'
        );
        $stmt->execute([
            'id' => $id,
            'tenant_id' => $tenantId,
        ]);
        $t = $stmt->fetch();
        if (!$t) {
            http_response_code(404);
            echo '404';
            return;
        }

        $occurredOn = (string)($t['occurred_on'] ?? '');
        $t['is_closed'] = self::isDateClosed($tenantId, $occurredOn) ? 1 : 0;

        $analyticsEnabled = self::analyticsEnabled($tenantId);
        $budgetsEnabled = self::budgetAllocationsEnabled($tenantId);
        $canSoftDelete = self::canSoftDeleteTreasury();
        $allocations = [];
        $allocatedCents = 0;
        if ($analyticsEnabled) {
            try {
                $stmt = $pdo->prepare(
                    'SELECT a.axis_id, a.value_id, a.amount_cents, ax.label AS axis_label, av.label AS value_label
                     FROM treasury_transaction_allocations a
                     JOIN treasury_analytics_axes ax ON ax.id = a.axis_id AND ax.tenant_id = a.tenant_id
                     JOIN treasury_analytics_values av ON av.id = a.value_id AND av.tenant_id = a.tenant_id
                     WHERE a.tenant_id = :tenant_id AND a.transaction_id = :tx
                     ORDER BY ax.position ASC, ax.label ASC, av.position ASC, av.label ASC'
                );
                $stmt->execute(['tenant_id' => $tenantId, 'tx' => $id]);
                $allocations = $stmt->fetchAll();
                foreach ($allocations as $a) {
                    $allocatedCents += (int)($a['amount_cents'] ?? 0);
                }
            } catch (\Throwable $e) {
                $allocations = [];
                $allocatedCents = 0;
            }
        }

        $budgets = [];
        $budgetAllocations = [];
        $budgetAllocatedCents = 0;
        if ($budgetsEnabled) {
            try {
                $stmt = $pdo->prepare('SELECT id, name, is_active FROM treasury_budgets WHERE tenant_id = :tenant_id ORDER BY is_active DESC, name ASC');
                $stmt->execute(['tenant_id' => $tenantId]);
                $budgets = $stmt->fetchAll();

                $stmt = $pdo->prepare(
                    'SELECT a.budget_id, a.amount_cents, b.name AS budget_name, b.is_active
                     FROM treasury_budget_allocations a
                     JOIN treasury_budgets b ON b.id = a.budget_id AND b.tenant_id = a.tenant_id
                     WHERE a.tenant_id = :tenant_id AND a.transaction_id = :tx
                     ORDER BY b.is_active DESC, b.name ASC'
                );
                $stmt->execute(['tenant_id' => $tenantId, 'tx' => $id]);
                $budgetAllocations = $stmt->fetchAll();
                foreach ($budgetAllocations as $a) {
                    $budgetAllocatedCents += (int)($a['amount_cents'] ?? 0);
                }
            } catch (\Throwable $e) {
                $budgets = [];
                $budgetAllocations = [];
                $budgetAllocatedCents = 0;
            }
        }

        $error = Session::flash('error');
        $flash = Session::flash('success');
        require base_path('views/treasury/edit.php');
    }

    public static function update(): void
    {
        Access::require('treasury', 'write');

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo '400';
            return;
        }

        $returnTo = (string)($_POST['return_to'] ?? ('/treasury/edit?id=' . $id));
        if (!str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//')) {
            $returnTo = '/treasury/edit?id=' . $id;
        }

        $pdo = Db::pdo();
        $tenantId = (int)$_SESSION['tenant_id'];

        $stmt = $pdo->prepare('SELECT type, amount_cents, occurred_on, is_cleared FROM treasury_transactions WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $current = $stmt->fetch();
        if (!$current) {
            http_response_code(404);
            echo '404';
            return;
        }
        $isCleared = ((int)($current['is_cleared'] ?? 0) === 1);
        self::requireNotClosed($tenantId, (string)($current['occurred_on'] ?? ''), 'Période clôturée : modification interdite.');

        $label = trim((string)($_POST['label'] ?? ''));
        $categoryId = (string)($_POST['category_id'] ?? '');
        $paymentMethod = trim((string)($_POST['payment_method'] ?? ''));
        $counterparty = trim((string)($_POST['counterparty'] ?? ''));
        $tierIdStr = (string)($_POST['tier_id'] ?? '');
        $reference = trim((string)($_POST['reference'] ?? ''));

        if ($label === '') {
            Session::flash('error', 'Libellé invalide.');
            redirect($returnTo);
        }

        if (!in_array($paymentMethod, ['', 'cash', 'card', 'transfer', 'check', 'other'], true)) {
            $paymentMethod = '';
        }

        $tierId = null;
        if ($tierIdStr !== '') {
            $tmp = (int)$tierIdStr;
            if ($tmp > 0) {
                $tierId = $tmp;
            }
        }

        if ($tierId !== null) {
            $stmt = $pdo->prepare('SELECT id FROM tiers WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
            $stmt->execute(['id' => $tierId, 'tenant_id' => $tenantId]);
            if (!$stmt->fetch()) {
                $tierId = null;
            }
        }

        if ($tierId === null && $counterparty !== '') {
            $tierId = self::findOrCreateFreeTierId($tenantId, $counterparty);
        }

        $categoryIdInt = null;
        if ($categoryId !== '') {
            $tmp = (int)$categoryId;
            if ($tmp > 0) {
                $categoryIdInt = $tmp;
            }
        }

        if (self::budgetAllocationsEnabled($tenantId) && $categoryIdInt === null) {
            $budgetIds = $_POST['budget_id'] ?? null;
            $budgetAmounts = $_POST['budget_amount'] ?? null;
            $budgetPercents = $_POST['budget_percent'] ?? null;

            $hasBudgetAlloc = false;
            if (is_array($budgetIds) || is_array($budgetAmounts) || is_array($budgetPercents)) {
                $budgetIds = is_array($budgetIds) ? $budgetIds : [];
                $budgetAmounts = is_array($budgetAmounts) ? $budgetAmounts : [];
                $budgetPercents = is_array($budgetPercents) ? $budgetPercents : [];
                $n = max(count($budgetIds), count($budgetAmounts), count($budgetPercents));
                for ($i = 0; $i < $n; $i++) {
                    $bid = (int)($budgetIds[$i] ?? 0);
                    if ($bid <= 0) {
                        continue;
                    }
                    $p = trim((string)($budgetPercents[$i] ?? ''));
                    $a = trim((string)($budgetAmounts[$i] ?? ''));
                    if ($p !== '' && (float)str_replace(',', '.', $p) > 0) {
                        $hasBudgetAlloc = true;
                        break;
                    }
                    if ($a !== '' && (float)str_replace(',', '.', $a) > 0) {
                        $hasBudgetAlloc = true;
                        break;
                    }
                }
            }

            if (!$hasBudgetAlloc) {
                Session::flash('error', 'Catégorie obligatoire si aucune ventilation budget.');
                redirect($returnTo);
            }
        }
        if ($categoryIdInt !== null) {
            $stmt = $pdo->prepare('SELECT id FROM treasury_categories WHERE id = :id AND tenant_id = :tenant_id');
            $stmt->execute(['id' => $categoryIdInt, 'tenant_id' => $tenantId]);
            if (!$stmt->fetch()) {
                Session::flash('error', 'Catégorie invalide.');
                redirect($returnTo);
            }
        }

        $newType = (string)($_POST['type'] ?? (string)($current['type'] ?? 'expense'));
        $newOccurredOn = (string)($_POST['occurred_on'] ?? (string)($current['occurred_on'] ?? ''));
        $newAmount = (string)($_POST['amount'] ?? '');

        if ($isCleared) {
            $newType = (string)($current['type'] ?? 'expense');
            $newOccurredOn = (string)($current['occurred_on'] ?? '');
            $amountCents = (int)($current['amount_cents'] ?? 0);
        } else {
            if (!in_array($newType, ['expense', 'income'], true)) {
                Session::flash('error', 'Type invalide.');
                redirect($returnTo);
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $newOccurredOn)) {
                Session::flash('error', 'Date invalide.');
                redirect($returnTo);
            }
            $amountCents = (int)round(((float)str_replace(',', '.', $newAmount)) * 100);
            if ($amountCents <= 0) {
                Session::flash('error', 'Montant invalide.');
                redirect($returnTo);
            }
        }

        $stmt = $pdo->prepare(
            'UPDATE treasury_transactions
             SET type = :type,
                 occurred_on = :occurred_on,
                 amount_cents = :amount_cents,
                 label = :label,
                 category_id = :category_id,
                 payment_method = :payment_method,
                 counterparty = :counterparty,
                 tier_id = :tier_id,
                 reference = :reference
             WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL'
        );
        $stmt->execute([
            'type' => $newType,
            'occurred_on' => $newOccurredOn,
            'amount_cents' => $amountCents,
            'label' => $label,
            'category_id' => $categoryIdInt,
            'payment_method' => ($paymentMethod !== '' ? $paymentMethod : null),
            'counterparty' => ($counterparty !== '' ? $counterparty : null),
            'tier_id' => $tierId,
            'reference' => ($reference !== '' ? $reference : null),
            'id' => $id,
            'tenant_id' => $tenantId,
        ]);

        if (self::analyticsEnabled($tenantId)) {
            $axes = $_POST['alloc_axis'] ?? null;
            $values = $_POST['alloc_value'] ?? null;
            $amounts = $_POST['alloc_amount'] ?? null;
            $percents = $_POST['alloc_percent'] ?? null;

            if (is_array($axes) || is_array($values) || is_array($amounts) || is_array($percents)) {
                $axes = is_array($axes) ? $axes : [];
                $values = is_array($values) ? $values : [];
                $amounts = is_array($amounts) ? $amounts : [];
                $percents = is_array($percents) ? $percents : [];

                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare('DELETE FROM treasury_transaction_allocations WHERE tenant_id = :tenant_id AND transaction_id = :tx');
                    $stmt->execute(['tenant_id' => $tenantId, 'tx' => $id]);

                    $n = max(count($axes), count($values), count($amounts), count($percents));
                    for ($i = 0; $i < $n; $i++) {
                        $axisLabel = trim((string)($axes[$i] ?? ''));
                        $valueLabel = trim((string)($values[$i] ?? ''));
                        if ($axisLabel === '' || $valueLabel === '') {
                            continue;
                        }

                        $p = trim((string)($percents[$i] ?? ''));
                        $a = trim((string)($amounts[$i] ?? ''));
                        $allocCents = 0;

                        if ($p !== '') {
                            $pNum = (float)str_replace(',', '.', $p);
                            if ($pNum <= 0) {
                                continue;
                            }
                            $allocCents = (int)round($amountCents * ($pNum / 100));
                        } elseif ($a !== '') {
                            $aNum = (float)str_replace(',', '.', $a);
                            $allocCents = (int)round($aNum * 100);
                        } else {
                            continue;
                        }

                        if ($allocCents <= 0) {
                            continue;
                        }

                        $axisKey = strtolower(trim(preg_replace('/[^a-zA-Z0-9_\-]+/', '_', $axisLabel) ?? $axisLabel));
                        if ($axisKey === '') {
                            $axisKey = 'axe';
                        }
                        if (mb_strlen($axisKey) > 64) {
                            $axisKey = mb_substr($axisKey, 0, 64);
                        }

                        $stmt = $pdo->prepare(
                            'INSERT INTO treasury_analytics_axes (tenant_id, `key`, label)
                             VALUES (:tenant_id, :k, :label)
                             ON DUPLICATE KEY UPDATE label = VALUES(label)'
                        );
                        $stmt->execute(['tenant_id' => $tenantId, 'k' => $axisKey, 'label' => $axisLabel]);

                        $stmt = $pdo->prepare('SELECT id FROM treasury_analytics_axes WHERE tenant_id = :tenant_id AND `key` = :k LIMIT 1');
                        $stmt->execute(['tenant_id' => $tenantId, 'k' => $axisKey]);
                        $axisRow = $stmt->fetch();
                        $axisId = (int)($axisRow['id'] ?? 0);
                        if ($axisId <= 0) {
                            continue;
                        }

                        $stmt = $pdo->prepare(
                            'INSERT INTO treasury_analytics_values (tenant_id, axis_id, label)
                             VALUES (:tenant_id, :axis_id, :label)
                             ON DUPLICATE KEY UPDATE label = VALUES(label)'
                        );
                        $stmt->execute(['tenant_id' => $tenantId, 'axis_id' => $axisId, 'label' => $valueLabel]);

                        $stmt = $pdo->prepare('SELECT id FROM treasury_analytics_values WHERE tenant_id = :tenant_id AND axis_id = :axis_id AND label = :label LIMIT 1');
                        $stmt->execute(['tenant_id' => $tenantId, 'axis_id' => $axisId, 'label' => $valueLabel]);
                        $valRow = $stmt->fetch();
                        $valueId = (int)($valRow['id'] ?? 0);
                        if ($valueId <= 0) {
                            continue;
                        }

                        $stmt = $pdo->prepare(
                            'INSERT INTO treasury_transaction_allocations (tenant_id, transaction_id, axis_id, value_id, amount_cents)
                             VALUES (:tenant_id, :tx, :axis_id, :value_id, :amount_cents)'
                        );
                        $stmt->execute([
                            'tenant_id' => $tenantId,
                            'tx' => $id,
                            'axis_id' => $axisId,
                            'value_id' => $valueId,
                            'amount_cents' => $allocCents,
                        ]);
                    }

                    $pdo->commit();
                } catch (\Throwable $e) {
                    $pdo->rollBack();
                }
            }
        }

        if (self::budgetAllocationsEnabled($tenantId)) {
            $budgetIds = $_POST['budget_id'] ?? null;
            $budgetAmounts = $_POST['budget_amount'] ?? null;
            $budgetPercents = $_POST['budget_percent'] ?? null;

            if (is_array($budgetIds) || is_array($budgetAmounts) || is_array($budgetPercents)) {
                $budgetIds = is_array($budgetIds) ? $budgetIds : [];
                $budgetAmounts = is_array($budgetAmounts) ? $budgetAmounts : [];
                $budgetPercents = is_array($budgetPercents) ? $budgetPercents : [];

                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare('DELETE FROM treasury_budget_allocations WHERE tenant_id = :tenant_id AND transaction_id = :tx');
                    $stmt->execute(['tenant_id' => $tenantId, 'tx' => $id]);

                    $n = max(count($budgetIds), count($budgetAmounts), count($budgetPercents));
                    for ($i = 0; $i < $n; $i++) {
                        $bid = (int)($budgetIds[$i] ?? 0);
                        if ($bid <= 0) {
                            continue;
                        }

                        $p = trim((string)($budgetPercents[$i] ?? ''));
                        $a = trim((string)($budgetAmounts[$i] ?? ''));
                        $allocCents = 0;

                        if ($p !== '') {
                            $pNum = (float)str_replace(',', '.', $p);
                            if ($pNum <= 0) {
                                continue;
                            }
                            $allocCents = (int)round($amountCents * ($pNum / 100));
                        } elseif ($a !== '') {
                            $aNum = (float)str_replace(',', '.', $a);
                            $allocCents = (int)round($aNum * 100);
                        } else {
                            continue;
                        }

                        if ($allocCents <= 0) {
                            continue;
                        }

                        $stmt = $pdo->prepare('SELECT id FROM treasury_budgets WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
                        $stmt->execute(['id' => $bid, 'tenant_id' => $tenantId]);
                        if (!$stmt->fetch()) {
                            continue;
                        }

                        $stmt = $pdo->prepare(
                            'INSERT INTO treasury_budget_allocations (tenant_id, transaction_id, budget_id, amount_cents)
                             VALUES (:tenant_id, :tx, :budget_id, :amount_cents)'
                        );
                        $stmt->execute([
                            'tenant_id' => $tenantId,
                            'tx' => $id,
                            'budget_id' => $bid,
                            'amount_cents' => $allocCents,
                        ]);
                    }

                    $pdo->commit();
                } catch (\Throwable $e) {
                    $pdo->rollBack();
                }
            }
        }

        Session::flash('success', $isCleared ? 'Transaction mise à jour (montant/date/type verrouillés car rapprochée).' : 'Transaction mise à jour.');
        redirect(tenant_path('/treasury/edit?id=' . $id));
    }

    public static function create(): void
    {
        Access::require('treasury', 'read');

        $tenantId = (int)$_SESSION['tenant_id'];
        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT id, name FROM treasury_categories WHERE tenant_id = :tenant_id ORDER BY name ASC');
        $stmt->execute(['tenant_id' => $tenantId]);
        $categories = $stmt->fetchAll();

        $budgetsEnabled = self::budgetAllocationsEnabled($tenantId);
        $budgets = [];
        if ($budgetsEnabled) {
            try {
                $stmt = $pdo->prepare('SELECT id, name, is_active FROM treasury_budgets WHERE tenant_id = :tenant_id AND is_active = 1 ORDER BY name ASC');
                $stmt->execute(['tenant_id' => $tenantId]);
                $budgets = $stmt->fetchAll();
            } catch (\Throwable $e) {
                $budgets = [];
            }
        }

        $prefill = [];
        $duplicateId = (int)($_GET['duplicate_id'] ?? 0);
        if ($duplicateId > 0) {
            $stmt = $pdo->prepare('SELECT type, amount_cents, label, category_id, payment_method, counterparty, reference FROM treasury_transactions WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL');
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
                    'payment_method' => $src['payment_method'] !== null ? (string)$src['payment_method'] : '',
                    'counterparty' => $src['counterparty'] !== null ? (string)$src['counterparty'] : '',
                    'reference' => $src['reference'] !== null ? (string)$src['reference'] : '',
                    'occurred_on' => date('Y-m-d'),
                ];
            }
        }

        $error = Session::flash('error');
        require base_path('views/treasury/new.php');
    }

    public static function tiersSearch(): void
    {
        Access::require('treasury', 'read');

        $tenantId = (int)($_SESSION['tenant_id'] ?? 0);
        if ($tenantId <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['items' => []]);
            return;
        }

        $q = trim((string)($_GET['q'] ?? ''));
        if ($q === '') {
            header('Content-Type: application/json');
            echo json_encode(['items' => []]);
            return;
        }

        if (mb_strlen($q) > 64) {
            $q = mb_substr($q, 0, 64);
        }

        $pdo = Db::pdo();

        $stmt = $pdo->prepare(
            'SELECT id, name
             FROM tiers
             WHERE tenant_id = :tenant_id
               AND (name LIKE :q OR normalized_name LIKE :q2)
             ORDER BY name ASC
             LIMIT 10'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'q' => '%' . $q . '%',
            'q2' => '%' . self::normalizeTierName($q) . '%',
        ]);
        $rows = $stmt->fetchAll();

        $items = [];
        foreach ($rows as $r) {
            $items[] = [
                'id' => (int)($r['id'] ?? 0),
                'name' => (string)($r['name'] ?? ''),
            ];
        }

        header('Content-Type: application/json');
        echo json_encode(['items' => $items]);
    }

    public static function toggleCleared(): void
    {
        Access::require('treasury', 'write');

        $id = (int)($_POST['id'] ?? 0);
        $returnTo = (string)($_POST['return_to'] ?? '/treasury');
        if ($id <= 0) {
            Session::flash('error', 'Transaction invalide.');
            redirect(tenant_path('/treasury'));
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT is_cleared, occurred_on FROM treasury_transactions WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL');
        $stmt->execute([
            'id' => $id,
            'tenant_id' => (int)$_SESSION['tenant_id'],
        ]);
        $row = $stmt->fetch();
        if (!$row) {
            Session::flash('error', 'Transaction introuvable.');
            redirect(tenant_path('/treasury'));
        }

        self::requireNotClosed((int)$_SESSION['tenant_id'], (string)($row['occurred_on'] ?? ''), 'Période clôturée : pointage interdit.');

        $isCleared = ((int)($row['is_cleared'] ?? 0) === 1);
        $newCleared = $isCleared ? 0 : 1;

        $stmt = $pdo->prepare('UPDATE treasury_transactions SET is_cleared = :is_cleared, cleared_at = :cleared_at, cleared_by_user_id = :cleared_by_user_id WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL');
        $stmt->execute([
            'is_cleared' => $newCleared,
            'cleared_at' => $newCleared === 1 ? date('Y-m-d H:i:s') : null,
            'cleared_by_user_id' => $newCleared === 1 ? (int)$_SESSION['user_id'] : null,
            'id' => $id,
            'tenant_id' => (int)$_SESSION['tenant_id'],
        ]);

        redirect(str_starts_with($returnTo, '/') ? $returnTo : tenant_path('/treasury'));
    }

    public static function store(): void
    {
        Access::require('treasury', 'write');

        $type = (string)($_POST['type'] ?? 'expense');
        $label = trim((string)($_POST['label'] ?? ''));
        $amount = (string)($_POST['amount'] ?? '');
        $date = (string)($_POST['occurred_on'] ?? '');
        $categoryId = (string)($_POST['category_id'] ?? '');
        $paymentMethod = trim((string)($_POST['payment_method'] ?? ''));
        $counterparty = trim((string)($_POST['counterparty'] ?? ''));
        $tierIdStr = (string)($_POST['tier_id'] ?? '');
        $reference = trim((string)($_POST['reference'] ?? ''));

        if (!in_array($paymentMethod, ['', 'cash', 'card', 'transfer', 'check', 'other'], true)) {
            $paymentMethod = '';
        }

        $tierId = null;
        if ($tierIdStr !== '') {
            $tmp = (int)$tierIdStr;
            if ($tmp > 0) {
                $tierId = $tmp;
            }
        }

        if (!in_array($type, ['expense', 'income'], true) || $label === '' || $amount === '' || $date === '') {
            Session::flash('error', 'Champs invalides.');
            redirect(tenant_path('/treasury/new'));
        }

        self::requireNotClosed((int)$_SESSION['tenant_id'], $date, 'Période clôturée : saisie interdite.');

        $amountCents = (int)round(((float)str_replace(',', '.', $amount)) * 100);
        if ($amountCents <= 0) {
            Session::flash('error', 'Montant invalide.');
            redirect(tenant_path('/treasury/new'));
        }

        $categoryIdInt = null;
        if ($categoryId !== '') {
            $categoryIdInt = (int)$categoryId;
            if ($categoryIdInt <= 0) {
                Session::flash('error', 'Catégorie invalide.');
                redirect(tenant_path('/treasury/new'));
            }

            $pdo = Db::pdo();
            $stmt = $pdo->prepare('SELECT id FROM treasury_categories WHERE id = :id AND tenant_id = :tenant_id');
            $stmt->execute([
                'id' => $categoryIdInt,
                'tenant_id' => (int)$_SESSION['tenant_id'],
            ]);
            if (!$stmt->fetch()) {
                Session::flash('error', 'Catégorie invalide.');
                redirect(tenant_path('/treasury/new'));
            }
        }

        if (self::budgetAllocationsEnabled($tenantId) && $categoryIdInt === null) {
            $budgetIds = $_POST['budget_id'] ?? null;
            $budgetAmounts = $_POST['budget_amount'] ?? null;
            $budgetPercents = $_POST['budget_percent'] ?? null;

            $hasBudgetAlloc = false;
            if (is_array($budgetIds) || is_array($budgetAmounts) || is_array($budgetPercents)) {
                $budgetIds = is_array($budgetIds) ? $budgetIds : [];
                $budgetAmounts = is_array($budgetAmounts) ? $budgetAmounts : [];
                $budgetPercents = is_array($budgetPercents) ? $budgetPercents : [];
                $n = max(count($budgetIds), count($budgetAmounts), count($budgetPercents));
                for ($i = 0; $i < $n; $i++) {
                    $bid = (int)($budgetIds[$i] ?? 0);
                    if ($bid <= 0) {
                        continue;
                    }
                    $p = trim((string)($budgetPercents[$i] ?? ''));
                    $a = trim((string)($budgetAmounts[$i] ?? ''));
                    if ($p !== '' && (float)str_replace(',', '.', $p) > 0) {
                        $hasBudgetAlloc = true;
                        break;
                    }
                    if ($a !== '' && (float)str_replace(',', '.', $a) > 0) {
                        $hasBudgetAlloc = true;
                        break;
                    }
                }
            }

            if (!$hasBudgetAlloc) {
                Session::flash('error', 'Catégorie obligatoire si aucune ventilation budget.');
                redirect(tenant_path('/treasury/new'));
            }
        }

        $pdo = Db::pdo();
        $tenantId = (int)$_SESSION['tenant_id'];

        if ($tierId !== null) {
            $stmt = $pdo->prepare('SELECT id FROM tiers WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
            $stmt->execute(['id' => $tierId, 'tenant_id' => $tenantId]);
            if (!$stmt->fetch()) {
                $tierId = null;
            }
        }

        if ($tierId === null && $counterparty !== '') {
            $tierId = self::findOrCreateFreeTierId($tenantId, $counterparty);
        }

        $stmt = $pdo->prepare('INSERT INTO treasury_transactions (tenant_id, created_by_user_id, type, payment_method, amount_cents, label, counterparty, tier_id, reference, occurred_on, category_id) VALUES (:tenant_id, :user_id, :type, :payment_method, :amount_cents, :label, :counterparty, :tier_id, :reference, :occurred_on, :category_id)');
        $stmt->execute([
            'tenant_id' => $tenantId,
            'user_id' => (int)$_SESSION['user_id'],
            'type' => $type,
            'payment_method' => ($paymentMethod !== '' ? $paymentMethod : null),
            'amount_cents' => $amountCents,
            'label' => $label,
            'counterparty' => ($counterparty !== '' ? $counterparty : null),
            'tier_id' => $tierId,
            'reference' => ($reference !== '' ? $reference : null),
            'occurred_on' => $date,
            'category_id' => $categoryIdInt,
        ]);

        $transactionId = (int)$pdo->lastInsertId();

        if (self::budgetAllocationsEnabled($tenantId)) {
            $budgetIds = $_POST['budget_id'] ?? null;
            $budgetAmounts = $_POST['budget_amount'] ?? null;
            $budgetPercents = $_POST['budget_percent'] ?? null;

            if (is_array($budgetIds) || is_array($budgetAmounts) || is_array($budgetPercents)) {
                $budgetIds = is_array($budgetIds) ? $budgetIds : [];
                $budgetAmounts = is_array($budgetAmounts) ? $budgetAmounts : [];
                $budgetPercents = is_array($budgetPercents) ? $budgetPercents : [];

                $pdo->beginTransaction();
                try {
                    $n = max(count($budgetIds), count($budgetAmounts), count($budgetPercents));
                    for ($i = 0; $i < $n; $i++) {
                        $bid = (int)($budgetIds[$i] ?? 0);
                        if ($bid <= 0) {
                            continue;
                        }

                        $p = trim((string)($budgetPercents[$i] ?? ''));
                        $a = trim((string)($budgetAmounts[$i] ?? ''));
                        $allocCents = 0;

                        if ($p !== '') {
                            $pNum = (float)str_replace(',', '.', $p);
                            if ($pNum <= 0) {
                                continue;
                            }
                            $allocCents = (int)round($amountCents * ($pNum / 100));
                        } elseif ($a !== '') {
                            $aNum = (float)str_replace(',', '.', $a);
                            $allocCents = (int)round($aNum * 100);
                        } else {
                            continue;
                        }

                        if ($allocCents <= 0) {
                            continue;
                        }

                        $stmt = $pdo->prepare('SELECT id FROM treasury_budgets WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
                        $stmt->execute(['id' => $bid, 'tenant_id' => $tenantId]);
                        if (!$stmt->fetch()) {
                            continue;
                        }

                        $stmt = $pdo->prepare(
                            'INSERT INTO treasury_budget_allocations (tenant_id, transaction_id, budget_id, amount_cents)
                             VALUES (:tenant_id, :tx, :budget_id, :amount_cents)'
                        );
                        $stmt->execute([
                            'tenant_id' => $tenantId,
                            'tx' => $transactionId,
                            'budget_id' => $bid,
                            'amount_cents' => $allocCents,
                        ]);
                    }

                    $pdo->commit();
                } catch (\Throwable $e) {
                    $pdo->rollBack();
                }
            }
        }

        $saved = TreasuryAttachmentsController::saveUploadedFiles((int)$_SESSION['tenant_id'], $transactionId, $_FILES['attachments'] ?? null);
        if ($saved > 0) {
            Session::flash('success', 'Transaction enregistrée + ' . $saved . ' justificatif(s).');
            redirect(tenant_path('/treasury/attachments?transaction_id=' . $transactionId));
        }

        Session::flash('success', 'Transaction enregistrée.');
        redirect(tenant_path('/treasury'));
    }

    public static function delete(): void
    {
        Access::require('treasury', 'write');

        if (!self::canSoftDeleteTreasury()) {
            http_response_code(403);
            echo '403';
            return;
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            Session::flash('error', 'Transaction invalide.');
            redirect('/treasury');
        }

        $returnTo = (string)($_POST['return_to'] ?? '/treasury');
        if (!str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//')) {
            $returnTo = '/treasury';
        }

        $reason = trim((string)($_POST['reason'] ?? ''));
        if (mb_strlen($reason) > 255) {
            $reason = mb_substr($reason, 0, 255);
        }

        $tenantId = (int)$_SESSION['tenant_id'];
        $userId = (int)$_SESSION['user_id'];
        $pdo = Db::pdo();

        $stmt = $pdo->prepare('SELECT id, is_cleared FROM treasury_transactions WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $tx = $stmt->fetch();
        if (!$tx) {
            Session::flash('error', 'Transaction introuvable.');
            redirect($returnTo);
        }

        $stmt = $pdo->prepare('SELECT occurred_on FROM treasury_transactions WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $stmt->fetch();
        if ($row) {
            self::requireNotClosed($tenantId, (string)($row['occurred_on'] ?? ''), 'Période clôturée : suppression interdite.');
        }

        if ((int)($tx['is_cleared'] ?? 0) === 1) {
            Session::flash('error', 'Impossible de supprimer une transaction rapprochée.');
            redirect($returnTo);
        }

        $stmt = $pdo->prepare('SELECT 1 FROM membership_subscriptions WHERE tenant_id = :tenant_id AND treasury_transaction_id = :tx LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId, 'tx' => $id]);
        if ($stmt->fetch()) {
            Session::flash('error', 'Impossible de supprimer : transaction liée à une cotisation.');
            redirect($returnTo);
        }

        $stmt = $pdo->prepare('SELECT 1 FROM treasury_attachments WHERE tenant_id = :tenant_id AND transaction_id = :tx LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId, 'tx' => $id]);
        if ($stmt->fetch()) {
            Session::flash('error', 'Impossible de supprimer : justificatif(s) présent(s).');
            redirect($returnTo);
        }

        $stmt = $pdo->prepare('UPDATE treasury_transactions SET deleted_at = :deleted_at, deleted_by_user_id = :user_id, delete_reason = :reason WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL');
        $stmt->execute([
            'deleted_at' => date('Y-m-d H:i:s'),
            'user_id' => $userId,
            'reason' => ($reason !== '' ? $reason : null),
            'id' => $id,
            'tenant_id' => $tenantId,
        ]);

        Session::flash('success', 'Transaction supprimée.');
        redirect('/treasury');
    }

    public static function exportCsv(): void
    {
        Access::require('treasury', 'read');

        $pdo = Db::pdo();
        $stmt = $pdo->prepare(
            'SELECT tt.id, tt.occurred_on, tt.type, tt.label, tt.amount_cents,
                    tt.payment_method, tt.counterparty, tt.reference, tt.is_cleared, tt.cleared_at,
                    tc.name AS category_name,
                    tc.account_code AS category_account_code,
                    tr.name AS tier_name
             FROM treasury_transactions tt
             LEFT JOIN treasury_categories tc ON tc.id = tt.category_id
             LEFT JOIN tiers tr ON tr.id = tt.tier_id AND tr.tenant_id = tt.tenant_id
             WHERE tt.tenant_id = :tenant_id
               AND tt.deleted_at IS NULL
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
        fputcsv($out, ['id', 'date', 'type', 'libelle', 'categorie', 'tiers', 'contrepartie', 'moyen_paiement', 'reference', 'rapprochee', 'rapprochee_le', 'montant_eur'], ';');

        while ($row = $stmt->fetch()) {
            $amountEur = number_format(((int)$row['amount_cents']) / 100, 2, ',', '');
            fputcsv($out, [
                (string)($row['id'] ?? ''),
                (string)$row['occurred_on'],
                (string)$row['type'],
                (string)$row['label'],
                (string)($row['category_name'] ?? ''),
                (string)($row['tier_name'] ?? ''),
                (string)($row['counterparty'] ?? ''),
                (string)($row['payment_method'] ?? ''),
                (string)($row['reference'] ?? ''),
                ((int)($row['is_cleared'] ?? 0) === 1 ? '1' : '0'),
                (string)($row['cleared_at'] ?? ''),
                $amountEur,
            ], ';');
        }

        fclose($out);
    }

    public static function exportZip(): void
    {
        Access::require('treasury', 'read');
        self::requireAdmin();

        if (!class_exists('ZipArchive')) {
            http_response_code(500);
            echo 'ZipArchive missing';
            return;
        }

        $tenantId = (int)$_SESSION['tenant_id'];
        $pdo = Db::pdo();

        $from = (string)($_GET['from'] ?? '');
        $to = (string)($_GET['to'] ?? '');
        $whereDates = '';
        $params = ['tenant_id' => $tenantId];

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            [$from, $to] = self::lastFiscalYearPeriod($tenantId, new \DateTimeImmutable('today'));
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $whereDates = ' AND tt.occurred_on >= :from AND tt.occurred_on <= :to ';
            $params['from'] = $from;
            $params['to'] = $to;
        }

        $stmt = $pdo->prepare(
            'SELECT tt.id, tt.occurred_on, tt.type, tt.label, tt.amount_cents,
                    tt.payment_method, tt.counterparty, tt.reference, tt.is_cleared, tt.cleared_at,
                    tc.name AS category_name,
                    tr.name AS tier_name
             FROM treasury_transactions tt
             LEFT JOIN treasury_categories tc ON tc.id = tt.category_id
             LEFT JOIN tiers tr ON tr.id = tt.tier_id AND tr.tenant_id = tt.tenant_id
             WHERE tt.tenant_id = :tenant_id
               AND tt.deleted_at IS NULL
               ' . $whereDates . '
             ORDER BY tt.occurred_on ASC, tt.id ASC'
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $tmpZip = tempnam(sys_get_temp_dir(), 'assofacile_treasury_');
        if ($tmpZip === false) {
            http_response_code(500);
            echo '500';
            return;
        }
        @unlink($tmpZip);
        $tmpZip .= '.zip';

        $zip = new \ZipArchive();
        if ($zip->open($tmpZip, \ZipArchive::CREATE) !== true) {
            http_response_code(500);
            echo '500';
            return;
        }

        $csv = fopen('php://temp', 'w+');
        fwrite($csv, "\xEF\xBB\xBF");
        fputcsv($csv, ['id', 'date', 'type', 'libelle', 'categorie', 'tiers', 'contrepartie', 'moyen_paiement', 'reference', 'rapprochee', 'rapprochee_le', 'montant_eur', 'nb_justificatifs', 'justificatifs_info'], ';');

        $fec = fopen('php://temp', 'w+');
        fwrite($fec, "\xEF\xBB\xBF");
        fputcsv(
            $fec,
            ['JournalCode', 'JournalLib', 'EcritureNum', 'EcritureDate', 'CompteNum', 'CompteLib', 'CompAuxLib', 'PieceRef', 'PieceDate', 'EcritureLib', 'Debit', 'Credit', 'DateLet', 'ValidDate', 'Montantdevise', 'Idevise', 'TxId', 'Categorie', 'Tiers', 'Rapprochee'],
            ';'
        );

        $attStmt = $pdo->prepare(
            'SELECT id, storage_driver, local_path, gdrive_file_id, original_name, mime_type, size_bytes
             FROM treasury_attachments
             WHERE tenant_id = :tenant_id AND transaction_id = :tx
             ORDER BY id ASC'
        );

        $driveService = null;
        $canFetchDrive = Modules::isEnabled($tenantId, 'drive')
            && GoogleDrive::isConfigured()
            && GoogleDrive::isAvailable()
            && GoogleDrive::isConnected($tenantId);
        if ($canFetchDrive) {
            $driveService = GoogleDrive::getService($tenantId);
        }

        $manifest = [
            'generated_at' => date('c'),
            'tenant_id' => $tenantId,
            'from' => $from,
            'to' => $to,
            'transactions' => [],
        ];

        foreach ($rows as $r) {
            $txId = (int)($r['id'] ?? 0);
            $attStmt->execute(['tenant_id' => $tenantId, 'tx' => $txId]);
            $atts = $attStmt->fetchAll();

            $nb = is_array($atts) ? count($atts) : 0;
            $infoParts = [];

            if ($nb > 0) {
                foreach ($atts as $a) {
                    $driver = (string)($a['storage_driver'] ?? 'local');
                    $orig = (string)($a['original_name'] ?? 'file');
                    $safeOrig = preg_replace('/[^a-zA-Z0-9._\- ]+/', '_', $orig) ?? $orig;
                    $safeOrig = trim($safeOrig);
                    if ($safeOrig === '') {
                        $safeOrig = 'file';
                    }

                    if ($driver === 'local' && !empty($a['local_path'])) {
                        $abs = Storage::privatePath((string)$a['local_path']);
                        if (is_file($abs)) {
                            $zipPath = 'attachments/tx_' . $txId . '/' . (int)($a['id'] ?? 0) . '_' . $safeOrig;
                            $zip->addFile($abs, $zipPath);
                            $infoParts[] = $zipPath;
                            continue;
                        }
                        $infoParts[] = 'local_missing:' . (string)($a['local_path'] ?? '');
                        continue;
                    }

                    if ($driver === 'gdrive') {
                        $fileId = (string)($a['gdrive_file_id'] ?? '');
                        if ($fileId !== '' && $driveService) {
                            try {
                                $response = $driveService->files->get($fileId, ['alt' => 'media']);
                                $body = $response->getBody();
                                $content = '';
                                while (!$body->eof()) {
                                    $content .= $body->read(8192);
                                    if (strlen($content) > 20 * 1024 * 1024) {
                                        break;
                                    }
                                }
                                if ($content !== '') {
                                    $zipPath = 'attachments/tx_' . $txId . '/' . (int)($a['id'] ?? 0) . '_' . $safeOrig;
                                    $zip->addFromString($zipPath, $content);
                                    $infoParts[] = $zipPath;
                                    continue;
                                }
                                $infoParts[] = 'gdrive_missing:' . $fileId . ':' . $safeOrig;
                                continue;
                            } catch (\Throwable $e) {
                                $infoParts[] = 'gdrive_error:' . $fileId . ':' . $safeOrig;
                                continue;
                            }
                        }

                        $infoParts[] = 'gdrive:' . $fileId . ':' . $safeOrig;
                        continue;
                    }
                }
            }

            $occurredOn = (string)($r['occurred_on'] ?? '');
            $label = (string)($r['label'] ?? '');
            $categoryName = (string)($r['category_name'] ?? '');
            $tierName = (string)($r['tier_name'] ?? '');
            $reference = (string)($r['reference'] ?? '');
            $paymentMethod = (string)($r['payment_method'] ?? '');
            $counterparty = (string)($r['counterparty'] ?? '');

            $pieceRef = $reference !== '' ? $reference : ('TX' . $txId);
            $ecritureLib = trim($label);
            if ($ecritureLib === '') {
                $ecritureLib = $pieceRef;
            }

            $journalCode = 'BQ';
            $journalLib = 'Banque';
            $ecritureNum = 'TX' . $txId;
            $pieceDate = $occurredOn;
            $validDate = $occurredOn;
            $dateLet = (string)($r['cleared_at'] ?? '');
            $rapprochee = ((int)($r['is_cleared'] ?? 0) === 1);

            $amountCents = (int)($r['amount_cents'] ?? 0);
            $amountAbs = abs($amountCents);
            $amountStr = number_format($amountAbs / 100, 2, '.', '');

            $bankAccount = '512000';
            $categoryAccountCode = trim((string)($r['category_account_code'] ?? ''));
            $counterAccount = $categoryAccountCode !== '' ? $categoryAccountCode : (((string)($r['type'] ?? '') === 'income') ? '700000' : '600000');

            if ((string)($r['type'] ?? '') === 'income') {
                fputcsv($fec, [$journalCode, $journalLib, $ecritureNum, $occurredOn, $bankAccount, 'Banque', $tierName !== '' ? $tierName : $counterparty, $pieceRef, $pieceDate, $ecritureLib, $amountStr, '0.00', $dateLet, $validDate, '', '', (string)$txId, $categoryName, $tierName, $rapprochee ? '1' : '0'], ';');
                fputcsv($fec, [$journalCode, $journalLib, $ecritureNum, $occurredOn, $counterAccount, $categoryName !== '' ? $categoryName : 'Produits', $tierName !== '' ? $tierName : $counterparty, $pieceRef, $pieceDate, $ecritureLib, '0.00', $amountStr, $dateLet, $validDate, '', '', (string)$txId, $categoryName, $tierName, $rapprochee ? '1' : '0'], ';');
            } else {
                fputcsv($fec, [$journalCode, $journalLib, $ecritureNum, $occurredOn, $counterAccount, $categoryName !== '' ? $categoryName : 'Charges', $tierName !== '' ? $tierName : $counterparty, $pieceRef, $pieceDate, $ecritureLib, $amountStr, '0.00', $dateLet, $validDate, '', '', (string)$txId, $categoryName, $tierName, $rapprochee ? '1' : '0'], ';');
                fputcsv($fec, [$journalCode, $journalLib, $ecritureNum, $occurredOn, $bankAccount, 'Banque', $tierName !== '' ? $tierName : $counterparty, $pieceRef, $pieceDate, $ecritureLib, '0.00', $amountStr, $dateLet, $validDate, '', '', (string)$txId, $categoryName, $tierName, $rapprochee ? '1' : '0'], ';');
            }

            $amountEur = number_format(((int)($r['amount_cents'] ?? 0)) / 100, 2, ',', '');
            fputcsv($csv, [
                (string)$txId,
                $occurredOn,
                (string)($r['type'] ?? ''),
                $label,
                $categoryName,
                $tierName,
                $counterparty,
                $paymentMethod,
                $reference,
                ((int)($r['is_cleared'] ?? 0) === 1 ? '1' : '0'),
                (string)($r['cleared_at'] ?? ''),
                $amountEur,
                (string)$nb,
                implode(' | ', $infoParts),
            ], ';');

            $manifest['transactions'][] = [
                'id' => $txId,
                'occurred_on' => $occurredOn,
                'type' => (string)($r['type'] ?? ''),
                'label' => $label,
                'amount_cents' => (int)($r['amount_cents'] ?? 0),
                'category' => $categoryName,
                'tier' => $tierName,
                'counterparty' => $counterparty,
                'payment_method' => $paymentMethod,
                'reference' => $reference,
                'attachments' => $infoParts,
            ];
        }

        rewind($csv);
        $csvContent = stream_get_contents($csv);
        fclose($csv);

        rewind($fec);
        $fecContent = stream_get_contents($fec);
        fclose($fec);

        $periodLabel = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) ? ($from . '_to_' . $to) : date('Ymd_His');
        $name = 'treasury_export_' . $periodLabel . '.csv';
        $zip->addFromString($name, $csvContent !== false ? $csvContent : '');

        $fecName = 'pre_fec_' . $periodLabel . '.csv';
        $zip->addFromString($fecName, $fecContent !== false ? $fecContent : '');

        $zip->addFromString('manifest.json', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $readme = "Export cabinet - Trésorerie\n";
        $readme .= "Période: {$from} -> {$to}\n";
        $readme .= "Fichiers:\n";
        $readme .= "- {$name}: export trésorerie détaillé\n";
        $readme .= "- {$fecName}: pré-FEC (double écriture)\n";
        $readme .= "- attachments/: pièces justificatives\n";
        $readme .= "- manifest.json: mapping écritures -> pièces\n";
        $readme .= "\nNotes pré-FEC:\n";
        $readme .= "- Compte banque utilisé: 512000\n";
        $readme .= "- Contrepartie par défaut: 600000 (dépense) / 700000 (recette)\n";
        $readme .= "- Le cabinet peut ajuster les comptes selon le plan comptable.\n";
        $zip->addFromString('README.txt', $readme);

        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="treasury_export_' . $periodLabel . '.zip"');
        header('Content-Length: ' . (string)filesize($tmpZip));
        readfile($tmpZip);
        @unlink($tmpZip);
    }

    public static function closures(): void
    {
        Access::require('treasury', 'read');
        self::requireAdmin();

        $tenantId = (int)$_SESSION['tenant_id'];
        $pdo = Db::pdo();

        $stmt = $pdo->prepare(
            'SELECT tc.id, tc.start_date, tc.end_date, tc.reason, tc.closed_at, tc.closed_by_user_id,
                    u.email AS closed_by_name,
                    (
                        SELECT COUNT(*)
                        FROM treasury_transactions tt
                        WHERE tt.tenant_id = tc.tenant_id
                          AND tt.deleted_at IS NULL
                          AND tt.occurred_on >= tc.start_date
                          AND tt.occurred_on <= tc.end_date
                    ) AS tx_count
             FROM treasury_closures tc
             LEFT JOIN users u ON u.id = tc.closed_by_user_id
             WHERE tc.tenant_id = :tenant_id
             ORDER BY tc.end_date DESC, tc.id DESC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        $closures = $stmt->fetchAll();

        $today = new \DateTimeImmutable('today');
        $defaultStart = $today->modify('first day of last month')->format('Y-m-d');
        $defaultEnd = $today->modify('last day of last month')->format('Y-m-d');

        $prefillStart = (string)($_GET['start_date'] ?? '');
        $prefillEnd = (string)($_GET['end_date'] ?? '');
        if (
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $prefillStart)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $prefillEnd)
            && $prefillStart <= $prefillEnd
        ) {
            $defaultStart = $prefillStart;
            $defaultEnd = $prefillEnd;
        }

        $flash = Session::flash('success');
        $error = Session::flash('error');
        require base_path('views/treasury/closures.php');
    }

    public static function closePeriod(): void
    {
        Access::require('treasury', 'read');
        self::requireAdmin();

        $tenantId = (int)$_SESSION['tenant_id'];
        $userId = (int)$_SESSION['user_id'];
        $start = trim((string)($_POST['start_date'] ?? ''));
        $end = trim((string)($_POST['end_date'] ?? ''));
        $reason = trim((string)($_POST['reason'] ?? ''));
        if (mb_strlen($reason) > 255) {
            $reason = mb_substr($reason, 0, 255);
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) || $start > $end) {
            Session::flash('error', 'Période invalide.');
            redirect(tenant_path('/treasury/closures'));
        }

        $pdo = Db::pdo();

        $stmt = $pdo->prepare(
            'SELECT 1 FROM treasury_closures
             WHERE tenant_id = :tenant_id
               AND NOT (end_date < :start OR start_date > :end)
             LIMIT 1'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'start' => $start, 'end' => $end]);
        if ($stmt->fetch()) {
            Session::flash('error', 'Chevauchement avec une clôture existante.');
            redirect(tenant_path('/treasury/closures'));
        }

        $stmt = $pdo->prepare(
            'INSERT INTO treasury_closures (tenant_id, start_date, end_date, reason, closed_at, closed_by_user_id)
             VALUES (:tenant_id, :start_date, :end_date, :reason, :closed_at, :closed_by_user_id)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'start_date' => $start,
            'end_date' => $end,
            'reason' => ($reason !== '' ? $reason : null),
            'closed_at' => date('Y-m-d H:i:s'),
            'closed_by_user_id' => $userId,
        ]);

        Session::flash('success', 'Période clôturée.');
        redirect(tenant_path('/treasury/closures'));
    }

    public static function deleteClosure(): void
    {
        Access::require('treasury', 'read');
        self::requireAdmin();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo '400';
            return;
        }

        $tenantId = (int)$_SESSION['tenant_id'];
        $pdo = Db::pdo();

        $stmt = $pdo->prepare('DELETE FROM treasury_closures WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);

        Session::flash('success', 'Période déclôturée.');
        redirect(tenant_path('/treasury/closures'));
    }
}
