<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Db;
use App\Support\Access;
use App\Support\Session;

final class ChildGroupsController
{
    public static function index(): void
    {
        Access::require('members', 'read');

        $tenantId = (int)$_SESSION['tenant_id'];
        $pdo = Db::pdo();

        $stmt = $pdo->prepare(
            'SELECT cg.id, cg.name, cg.created_at,
                    (SELECT COUNT(*) FROM child_group_members cgm
                     INNER JOIN members m ON m.id = cgm.member_id
                     WHERE cgm.group_id = cg.id AND m.tenant_id = cg.tenant_id) AS children_count,
                    (SELECT COUNT(*) FROM child_group_staff cgs
                     WHERE cgs.group_id = cg.id) AS staff_count
             FROM child_groups cg
             WHERE cg.tenant_id = :tenant_id
             ORDER BY cg.id DESC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        $groups = $stmt->fetchAll();

        $flash = Session::flash('success');
        $error = Session::flash('error');
        require base_path('views/child_groups/index.php');
    }

    public static function create(): void
    {
        Access::require('members', 'write');

        $error = Session::flash('error');
        require base_path('views/child_groups/new.php');
    }

    public static function store(): void
    {
        Access::require('members', 'write');

        $tenantId = (int)$_SESSION['tenant_id'];
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            Session::flash('error', 'Nom requis.');
            redirect('/child-groups/new');
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('INSERT INTO child_groups (tenant_id, name) VALUES (:tenant_id, :name)');
        $stmt->execute([
            'tenant_id' => $tenantId,
            'name' => $name,
        ]);

        Session::flash('success', 'Groupe créé.');
        redirect('/child-groups');
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
        $stmt = $pdo->prepare('SELECT * FROM child_groups WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
        $stmt->execute([
            'id' => $id,
            'tenant_id' => $tenantId,
        ]);
        $group = $stmt->fetch();
        if (!$group) {
            http_response_code(404);
            echo '404';
            return;
        }

        $children = $pdo->prepare(
            'SELECT id, first_name, last_name
             FROM members
             WHERE tenant_id = :tenant_id AND relationship = \'child\'
             ORDER BY last_name ASC, first_name ASC, id DESC'
        );
        $children->execute(['tenant_id' => $tenantId]);
        $children = $children->fetchAll();

        $users = $pdo->prepare('SELECT id, email, full_name, role, is_admin FROM users WHERE tenant_id = :tenant_id AND is_active = 1 ORDER BY is_admin DESC, email ASC');
        $users->execute(['tenant_id' => $tenantId]);
        $users = $users->fetchAll();

        $stmt = $pdo->prepare('SELECT member_id FROM child_group_members WHERE group_id = :group_id');
        $stmt->execute(['group_id' => $id]);
        $childIds = array_map(static fn($r) => (int)$r['member_id'], $stmt->fetchAll());
        $childIds = array_fill_keys($childIds, true);

        $stmt = $pdo->prepare('SELECT user_id FROM child_group_staff WHERE group_id = :group_id');
        $stmt->execute(['group_id' => $id]);
        $staffIds = array_map(static fn($r) => (int)$r['user_id'], $stmt->fetchAll());
        $staffIds = array_fill_keys($staffIds, true);

        $flash = Session::flash('success');
        $error = Session::flash('error');
        require base_path('views/child_groups/edit.php');
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
        if ($name === '') {
            Session::flash('error', 'Nom requis.');
            redirect('/child-groups/edit?id=' . $id);
        }

        $postedChildren = $_POST['children'] ?? [];
        $postedStaff = $_POST['staff'] ?? [];
        $postedChildren = is_array($postedChildren) ? array_keys($postedChildren) : [];
        $postedStaff = is_array($postedStaff) ? array_keys($postedStaff) : [];

        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT id FROM child_groups WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
            $stmt->execute([
                'id' => $id,
                'tenant_id' => $tenantId,
            ]);
            if (!$stmt->fetch()) {
                $pdo->rollBack();
                http_response_code(404);
                echo '404';
                return;
            }

            $stmt = $pdo->prepare('UPDATE child_groups SET name = :name WHERE id = :id AND tenant_id = :tenant_id');
            $stmt->execute([
                'name' => $name,
                'id' => $id,
                'tenant_id' => $tenantId,
            ]);

            $pdo->prepare('DELETE FROM child_group_members WHERE group_id = :group_id')->execute(['group_id' => $id]);
            $pdo->prepare('DELETE FROM child_group_staff WHERE group_id = :group_id')->execute(['group_id' => $id]);

            if (!empty($postedChildren)) {
                $ins = $pdo->prepare('INSERT IGNORE INTO child_group_members (group_id, member_id) VALUES (:group_id, :member_id)');
                foreach ($postedChildren as $memberIdStr) {
                    $memberId = (int)$memberIdStr;
                    if ($memberId <= 0) {
                        continue;
                    }
                    $stmt = $pdo->prepare('SELECT id FROM members WHERE id = :id AND tenant_id = :tenant_id AND relationship = \'child\' LIMIT 1');
                    $stmt->execute(['id' => $memberId, 'tenant_id' => $tenantId]);
                    if (!$stmt->fetch()) {
                        continue;
                    }
                    $ins->execute(['group_id' => $id, 'member_id' => $memberId]);
                }
            }

            if (!empty($postedStaff)) {
                $ins = $pdo->prepare('INSERT IGNORE INTO child_group_staff (group_id, user_id) VALUES (:group_id, :user_id)');
                foreach ($postedStaff as $userIdStr) {
                    $userId = (int)$userIdStr;
                    if ($userId <= 0) {
                        continue;
                    }
                    $stmt = $pdo->prepare('SELECT id FROM users WHERE id = :id AND tenant_id = :tenant_id AND is_active = 1 LIMIT 1');
                    $stmt->execute(['id' => $userId, 'tenant_id' => $tenantId]);
                    if (!$stmt->fetch()) {
                        continue;
                    }
                    $ins->execute(['group_id' => $id, 'user_id' => $userId]);
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        Session::flash('success', 'Groupe mis à jour.');
        redirect('/child-groups/edit?id=' . $id);
    }
}
