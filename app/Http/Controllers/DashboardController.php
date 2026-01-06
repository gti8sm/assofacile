<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Db;
use App\Support\Access;
use App\Support\Modules;

final class DashboardController
{
    public static function index(): void
    {
        if (!isset($_SESSION['user_id'], $_SESSION['tenant_id'])) {
            redirect('/login');
        }

        $pdo = Db::pdo();
        $tenantId = (int)$_SESSION['tenant_id'];
        $userId = (int)$_SESSION['user_id'];
        $stmt = $pdo->prepare('SELECT name FROM tenants WHERE id = :id');
        $stmt->execute(['id' => $tenantId]);
        $tenant = $stmt->fetch();

        $stats = [];
        $recent = [
            'members' => [],
            'treasury' => [],
            'memberships' => [],
        ];
        $quickActions = [];

        if (Modules::isEnabled($tenantId, 'members') && Access::can($tenantId, $userId, 'members', 'read')) {
            $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM members WHERE tenant_id = :tenant_id AND status = 'active'");
            $stmt->execute(['tenant_id' => $tenantId]);
            $row = $stmt->fetch();
            $stats['members_active'] = (int)($row['c'] ?? 0);

            $quickActions[] = [
                'label' => 'Nouvel adhérent',
                'href' => '/members/new',
            ];

            try {
                $stmt = $pdo->prepare(
                    'SELECT id, first_name, last_name, status, created_at
                     FROM members
                     WHERE tenant_id = :tenant_id
                     ORDER BY id DESC
                     LIMIT 5'
                );
                $stmt->execute(['tenant_id' => $tenantId]);
                $recent['members'] = $stmt->fetchAll();
            } catch (\Throwable $e) {
                $recent['members'] = [];
            }

            $stmt = $pdo->prepare('SELECT COUNT(*) AS c FROM households WHERE tenant_id = :tenant_id');
            try {
                $stmt->execute(['tenant_id' => $tenantId]);
                $row = $stmt->fetch();
                $stats['households'] = (int)($row['c'] ?? 0);
            } catch (\Throwable $e) {
                $stats['households'] = null;
            }
        }

        if (Modules::isEnabled($tenantId, 'members') && Access::can($tenantId, $userId, 'members', 'read')) {
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM membership_subscriptions WHERE tenant_id = :tenant_id AND status = 'paid'");
                $stmt->execute(['tenant_id' => $tenantId]);
                $row = $stmt->fetch();
                $stats['memberships_paid'] = (int)($row['c'] ?? 0);

                $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM membership_subscriptions WHERE tenant_id = :tenant_id AND status = 'pending'");
                $stmt->execute(['tenant_id' => $tenantId]);
                $row = $stmt->fetch();
                $stats['memberships_pending'] = (int)($row['c'] ?? 0);

                $quickActions[] = [
                    'label' => 'Cotisations',
                    'href' => '/memberships/products',
                ];

                $stmt = $pdo->prepare(
                    'SELECT ms.id, ms.status, ms.amount_cents, ms.start_date, ms.end_date, ms.created_at,
                            ms.member_id, ms.household_id,
                            CONCAT(COALESCE(m.first_name, \\"\\"), \\" \\", COALESCE(m.last_name, \\"\\")) AS member_name,
                            h.name AS household_name
                     FROM membership_subscriptions ms
                     LEFT JOIN members m
                       ON m.id = ms.member_id AND m.tenant_id = ms.tenant_id
                     LEFT JOIN households h
                       ON h.id = ms.household_id AND h.tenant_id = ms.tenant_id
                     WHERE ms.tenant_id = :tenant_id
                     ORDER BY ms.id DESC
                     LIMIT 5'
                );
                $stmt->execute(['tenant_id' => $tenantId]);
                $recent['memberships'] = $stmt->fetchAll();
            } catch (\Throwable $e) {
                $stats['memberships_paid'] = null;
                $stats['memberships_pending'] = null;
                $recent['memberships'] = [];
            }
        }

        if (Modules::isEnabled($tenantId, 'treasury') && Access::can($tenantId, $userId, 'treasury', 'read')) {
            try {
                $stmt = $pdo->prepare("SELECT type, COALESCE(SUM(amount_cents),0) AS s FROM treasury_transactions WHERE tenant_id = :tenant_id AND deleted_at IS NULL GROUP BY type");
                $stmt->execute(['tenant_id' => $tenantId]);
                $rows = $stmt->fetchAll();

                $income = 0;
                $expense = 0;
                foreach ($rows as $r) {
                    if (!is_array($r)) {
                        continue;
                    }
                    $t = (string)($r['type'] ?? '');
                    $s = (int)($r['s'] ?? 0);
                    if ($t === 'income') {
                        $income = $s;
                    } elseif ($t === 'expense') {
                        $expense = $s;
                    }
                }
                $stats['treasury_income_total_cents'] = $income;
                $stats['treasury_expense_total_cents'] = $expense;
                $stats['treasury_balance_total_cents'] = $income - $expense;

                $stmt = $pdo->prepare("SELECT type, COALESCE(SUM(amount_cents),0) AS s FROM treasury_transactions WHERE tenant_id = :tenant_id AND deleted_at IS NULL AND occurred_on >= :from_date GROUP BY type");
                $stmt->execute([
                    'tenant_id' => $tenantId,
                    'from_date' => date('Y-m-01'),
                ]);
                $rows = $stmt->fetchAll();
                $incomeM = 0;
                $expenseM = 0;
                foreach ($rows as $r) {
                    if (!is_array($r)) {
                        continue;
                    }
                    $t = (string)($r['type'] ?? '');
                    $s = (int)($r['s'] ?? 0);
                    if ($t === 'income') {
                        $incomeM = $s;
                    } elseif ($t === 'expense') {
                        $expenseM = $s;
                    }
                }
                $stats['treasury_income_month_cents'] = $incomeM;
                $stats['treasury_expense_month_cents'] = $expenseM;
                $stats['treasury_balance_month_cents'] = $incomeM - $expenseM;

                $quickActions[] = [
                    'label' => 'Nouvelle transaction',
                    'href' => '/treasury/new',
                ];

                $stmt = $pdo->prepare(
                    'SELECT id, type, amount_cents, label, occurred_on
                     FROM treasury_transactions
                     WHERE tenant_id = :tenant_id
                       AND deleted_at IS NULL
                     ORDER BY occurred_on DESC, id DESC
                     LIMIT 5'
                );
                $stmt->execute(['tenant_id' => $tenantId]);
                $recent['treasury'] = $stmt->fetchAll();
            } catch (\Throwable $e) {
                $stats['treasury_income_total_cents'] = null;
                $stats['treasury_expense_total_cents'] = null;
                $stats['treasury_balance_total_cents'] = null;
                $stats['treasury_income_month_cents'] = null;
                $stats['treasury_expense_month_cents'] = null;
                $stats['treasury_balance_month_cents'] = null;
                $recent['treasury'] = [];
            }
        }

        require base_path('views/dashboard/index.php');
    }
}
