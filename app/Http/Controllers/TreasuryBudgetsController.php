<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Db;
use App\Support\Access;
use App\Support\ModuleSettings;
use App\Support\Session;

final class TreasuryBudgetsController
{
    private static function guard(): void
    {
        Access::require('treasury', 'read');

        $tenantId = (int)($_SESSION['tenant_id'] ?? 0);
        if ($tenantId <= 0 || !ModuleSettings::getBool($tenantId, 'treasury', 'budget_allocation_enabled', false)) {
            Session::flash('error', 'Budgets désactivés (Admin → Modules → Trésorerie).');
            redirect(tenant_path('/admin/modules/settings?module=treasury'));
        }
    }

    public static function index(): void
    {
        self::guard();

        $tenantId = (int)$_SESSION['tenant_id'];
        $pdo = Db::pdo();

        $stmt = $pdo->prepare('SELECT id, name, is_active, created_at FROM treasury_budgets WHERE tenant_id = :tenant_id ORDER BY is_active DESC, name ASC');
        $stmt->execute(['tenant_id' => $tenantId]);
        $budgets = $stmt->fetchAll();

        $stmt = $pdo->prepare('SELECT id, name FROM projects WHERE tenant_id = :tenant_id ORDER BY created_at DESC');
        $stmt->execute(['tenant_id' => $tenantId]);
        $projects = $stmt->fetchAll();

        $budgetProjectsByBudgetId = [];
        try {
            $stmt = $pdo->prepare(
                'SELECT bp.budget_id, p.id AS project_id, p.name AS project_name
                 FROM treasury_budget_projects bp
                 JOIN projects p ON p.id = bp.project_id AND p.tenant_id = bp.tenant_id
                 WHERE bp.tenant_id = :tenant_id
                 ORDER BY p.created_at DESC'
            );
            $stmt->execute(['tenant_id' => $tenantId]);
            $rows = $stmt->fetchAll();
            foreach ($rows as $r) {
                $bid = (int)($r['budget_id'] ?? 0);
                $pid = (int)($r['project_id'] ?? 0);
                if ($bid <= 0 || $pid <= 0) {
                    continue;
                }
                if (!isset($budgetProjectsByBudgetId[$bid])) {
                    $budgetProjectsByBudgetId[$bid] = [];
                }
                $budgetProjectsByBudgetId[$bid][] = [
                    'id' => $pid,
                    'name' => (string)($r['project_name'] ?? ''),
                ];
            }
        } catch (\Throwable $e) {
            $budgetProjectsByBudgetId = [];
        }

        $usageStmt = $pdo->prepare(
            'SELECT COUNT(*) AS c
             FROM treasury_budget_allocations a
             JOIN treasury_transactions tt
               ON tt.id = a.transaction_id AND tt.tenant_id = a.tenant_id
             WHERE a.tenant_id = :tenant_id AND a.budget_id = :budget_id AND tt.deleted_at IS NULL'
        );

        $usageCountById = [];
        foreach ($budgets as $b) {
            $bid = (int)($b['id'] ?? 0);
            if ($bid <= 0) {
                continue;
            }
            $usageStmt->execute(['tenant_id' => $tenantId, 'budget_id' => $bid]);
            $row = $usageStmt->fetch();
            $usageCountById[$bid] = (int)($row['c'] ?? 0);
        }

        $flash = Session::flash('success');
        $error = Session::flash('error');
        require base_path('views/treasury/budgets.php');
    }

    public static function linkProject(): void
    {
        Access::require('treasury', 'write');
        self::guard();

        $budgetId = (int)($_POST['budget_id'] ?? 0);
        if ($budgetId <= 0) {
            Session::flash('error', 'Budget invalide.');
            redirect(tenant_path('/treasury/budgets'));
        }

        $projectIdRaw = (string)($_POST['project_id'] ?? '');
        $projectId = null;
        if ($projectIdRaw !== '') {
            $tmp = (int)$projectIdRaw;
            if ($tmp > 0) {
                $projectId = $tmp;
            }
        }

        if ($projectId === null) {
            Session::flash('error', 'Projet invalide.');
            redirect(tenant_path('/treasury/budgets'));
        }

        $tenantId = (int)$_SESSION['tenant_id'];
        $pdo = Db::pdo();

        $stmt = $pdo->prepare('SELECT 1 FROM treasury_budgets WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
        $stmt->execute(['id' => $budgetId, 'tenant_id' => $tenantId]);
        if (!$stmt->fetch()) {
            Session::flash('error', 'Budget introuvable.');
            redirect(tenant_path('/treasury/budgets'));
        }

        if ($projectId !== null) {
            $stmt = $pdo->prepare('SELECT 1 FROM projects WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
            $stmt->execute(['id' => $projectId, 'tenant_id' => $tenantId]);
            if (!$stmt->fetch()) {
                Session::flash('error', 'Projet introuvable.');
                redirect(tenant_path('/treasury/budgets'));
            }
        }

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO treasury_budget_projects (tenant_id, budget_id, project_id)
                 VALUES (:tenant_id, :budget_id, :project_id)'
            );
            $stmt->execute([
                'tenant_id' => $tenantId,
                'budget_id' => $budgetId,
                'project_id' => $projectId,
            ]);
        } catch (\Throwable $e) {
        }

        Session::flash('success', 'Budget mis à jour.');
        redirect(tenant_path('/treasury/budgets'));
    }

    public static function unlinkProject(): void
    {
        Access::require('treasury', 'write');
        self::guard();

        $budgetId = (int)($_POST['budget_id'] ?? 0);
        $projectId = (int)($_POST['project_id'] ?? 0);
        if ($budgetId <= 0 || $projectId <= 0) {
            Session::flash('error', 'Liaison invalide.');
            redirect(tenant_path('/treasury/budgets'));
        }

        $tenantId = (int)$_SESSION['tenant_id'];
        $pdo = Db::pdo();

        $stmt = $pdo->prepare(
            'DELETE FROM treasury_budget_projects
             WHERE tenant_id = :tenant_id AND budget_id = :budget_id AND project_id = :project_id'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'budget_id' => $budgetId,
            'project_id' => $projectId,
        ]);

        Session::flash('success', 'Liaison supprimée.');
        redirect(tenant_path('/treasury/budgets'));
    }

    public static function store(): void
    {
        Access::require('treasury', 'write');
        self::guard();

        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 190) {
            Session::flash('error', 'Nom invalide.');
            redirect(tenant_path('/treasury/budgets'));
        }

        $tenantId = (int)$_SESSION['tenant_id'];
        $pdo = Db::pdo();

        try {
            $stmt = $pdo->prepare('INSERT INTO treasury_budgets (tenant_id, name, is_active) VALUES (:tenant_id, :name, 1)');
            $stmt->execute(['tenant_id' => $tenantId, 'name' => $name]);
        } catch (\Throwable $e) {
            Session::flash('error', 'Budget déjà existant ou erreur.');
            redirect(tenant_path('/treasury/budgets'));
        }

        Session::flash('success', 'Budget créé.');
        redirect(tenant_path('/treasury/budgets'));
    }

    public static function archive(): void
    {
        Access::require('treasury', 'write');
        self::guard();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            Session::flash('error', 'Budget invalide.');
            redirect(tenant_path('/treasury/budgets'));
        }

        $tenantId = (int)$_SESSION['tenant_id'];
        $pdo = Db::pdo();
        $stmt = $pdo->prepare('UPDATE treasury_budgets SET is_active = 0 WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);

        Session::flash('success', 'Budget archivé.');
        redirect(tenant_path('/treasury/budgets'));
    }

    public static function unarchive(): void
    {
        Access::require('treasury', 'write');
        self::guard();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            Session::flash('error', 'Budget invalide.');
            redirect(tenant_path('/treasury/budgets'));
        }

        $tenantId = (int)$_SESSION['tenant_id'];
        $pdo = Db::pdo();
        $stmt = $pdo->prepare('UPDATE treasury_budgets SET is_active = 1 WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);

        Session::flash('success', 'Budget réactivé.');
        redirect(tenant_path('/treasury/budgets'));
    }

    public static function transfer(): void
    {
        Access::require('treasury', 'write');
        self::guard();

        $fromId = (int)($_POST['from_budget_id'] ?? 0);
        $toId = (int)($_POST['to_budget_id'] ?? 0);
        if ($fromId <= 0 || $toId <= 0 || $fromId === $toId) {
            Session::flash('error', 'Transfert invalide.');
            redirect(tenant_path('/treasury/budgets'));
        }

        $tenantId = (int)$_SESSION['tenant_id'];
        $pdo = Db::pdo();

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) AS c
                 FROM treasury_budgets
                 WHERE tenant_id = :tenant_id
                   AND (id = :from_id OR id = :to_id)'
            );
            $stmt->execute(['tenant_id' => $tenantId, 'from_id' => $fromId, 'to_id' => $toId]);
            $row = $stmt->fetch();
            if ((int)($row['c'] ?? 0) < 2) {
                throw new \RuntimeException('Budget introuvable');
            }

            $stmt = $pdo->prepare(
                'SELECT transaction_id, amount_cents
                 FROM treasury_budget_allocations
                 WHERE tenant_id = :tenant_id AND budget_id = :from_id'
            );
            $stmt->execute(['tenant_id' => $tenantId, 'from_id' => $fromId]);
            $allocs = $stmt->fetchAll();

            $upsert = $pdo->prepare(
                'INSERT INTO treasury_budget_allocations (tenant_id, transaction_id, budget_id, amount_cents)
                 VALUES (:tenant_id, :tx, :budget_id, :amount_cents)
                 ON DUPLICATE KEY UPDATE amount_cents = amount_cents + VALUES(amount_cents)'
            );

            foreach ($allocs as $a) {
                $tx = (int)($a['transaction_id'] ?? 0);
                $amt = (int)($a['amount_cents'] ?? 0);
                if ($tx <= 0 || $amt <= 0) {
                    continue;
                }
                $upsert->execute([
                    'tenant_id' => $tenantId,
                    'tx' => $tx,
                    'budget_id' => $toId,
                    'amount_cents' => $amt,
                ]);
            }

            $del = $pdo->prepare('DELETE FROM treasury_budget_allocations WHERE tenant_id = :tenant_id AND budget_id = :from_id');
            $del->execute(['tenant_id' => $tenantId, 'from_id' => $fromId]);

            $arch = $pdo->prepare('UPDATE treasury_budgets SET is_active = 0 WHERE id = :id AND tenant_id = :tenant_id');
            $arch->execute(['id' => $fromId, 'tenant_id' => $tenantId]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Session::flash('error', 'Erreur transfert.');
            redirect(tenant_path('/treasury/budgets'));
        }

        Session::flash('success', 'Transfert effectué (budget source archivé).');
        redirect(tenant_path('/treasury/budgets'));
    }

    public static function delete(): void
    {
        Access::require('treasury', 'write');
        self::guard();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            Session::flash('error', 'Budget invalide.');
            redirect(tenant_path('/treasury/budgets'));
        }

        $tenantId = (int)$_SESSION['tenant_id'];
        $pdo = Db::pdo();

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS c
             FROM treasury_budget_allocations a
             JOIN treasury_transactions tt
               ON tt.id = a.transaction_id AND tt.tenant_id = a.tenant_id
             WHERE a.tenant_id = :tenant_id AND a.budget_id = :budget_id AND tt.deleted_at IS NULL'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'budget_id' => $id]);
        $row = $stmt->fetch();
        if ((int)($row['c'] ?? 0) > 0) {
            Session::flash('error', 'Suppression interdite : budget utilisé.');
            redirect(tenant_path('/treasury/budgets'));
        }

        $stmt = $pdo->prepare('DELETE FROM treasury_budgets WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);

        Session::flash('success', 'Budget supprimé.');
        redirect(tenant_path('/treasury/budgets'));
    }
}
