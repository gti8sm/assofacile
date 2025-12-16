<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Db;
use App\Support\Access;
use App\Support\Session;

final class MembersController
{
    public static function index(): void
    {
        Access::require('members', 'read');

        $tenantId = (int)$_SESSION['tenant_id'];
        $q = trim((string)($_GET['q'] ?? ''));
        $status = (string)($_GET['status'] ?? '');
        if (!in_array($status, ['', 'active', 'inactive'], true)) {
            $status = '';
        }

        $sql = 'SELECT id, first_name, last_name, email, phone, status, member_since, membership_paid_until, created_at
                FROM members
                WHERE tenant_id = :tenant_id';
        $params = ['tenant_id' => $tenantId];

        if ($status !== '') {
            $sql .= ' AND status = :status';
            $params['status'] = $status;
        }

        if ($q !== '') {
            $sql .= ' AND (
                CONCAT(COALESCE(first_name, \'\'), \' \', COALESCE(last_name, \'\')) LIKE :q
                OR COALESCE(email, \'\') LIKE :q
                OR COALESCE(phone, \'\') LIKE :q
            )';
            $params['q'] = '%' . $q . '%';
        }

        $sql .= ' ORDER BY status ASC, last_name ASC, first_name ASC, id DESC';

        $pdo = Db::pdo();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $members = $stmt->fetchAll();

        $flash = Session::flash('success');
        $error = Session::flash('error');

        require base_path('views/members/index.php');
    }

    public static function create(): void
    {
        Access::require('members', 'write');

        $error = Session::flash('error');
        require base_path('views/members/new.php');
    }

    public static function store(): void
    {
        Access::require('members', 'write');

        $tenantId = (int)$_SESSION['tenant_id'];

        $first = trim((string)($_POST['first_name'] ?? ''));
        $last = trim((string)($_POST['last_name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $memberSince = trim((string)($_POST['member_since'] ?? ''));
        $paidUntil = trim((string)($_POST['membership_paid_until'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            Session::flash('error', 'Email invalide.');
            redirect('/members/new');
        }

        if ($memberSince !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $memberSince)) {
            Session::flash('error', 'Date d\'adhésion invalide.');
            redirect('/members/new');
        }

        if ($paidUntil !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $paidUntil)) {
            Session::flash('error', 'Date de cotisation invalide.');
            redirect('/members/new');
        }

        $pdo = Db::pdo();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO members (tenant_id, first_name, last_name, email, phone, status, member_since, membership_paid_until, notes)
                 VALUES (:tenant_id, :first_name, :last_name, :email, :phone, :status, :member_since, :membership_paid_until, :notes)'
            );
            $stmt->execute([
                'tenant_id' => $tenantId,
                'first_name' => ($first !== '' ? $first : null),
                'last_name' => ($last !== '' ? $last : null),
                'email' => ($email !== '' ? $email : null),
                'phone' => ($phone !== '' ? $phone : null),
                'status' => 'active',
                'member_since' => ($memberSince !== '' ? $memberSince : null),
                'membership_paid_until' => ($paidUntil !== '' ? $paidUntil : null),
                'notes' => ($notes !== '' ? $notes : null),
            ]);
        } catch (\Throwable $e) {
            Session::flash('error', 'Erreur lors de la création (email déjà utilisé ?).');
            redirect('/members/new');
        }

        Session::flash('success', 'Adhérent ajouté.');
        redirect('/members');
    }

    public static function edit(): void
    {
        Access::require('members', 'write');

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo '400';
            return;
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT * FROM members WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
        $stmt->execute([
            'id' => $id,
            'tenant_id' => (int)$_SESSION['tenant_id'],
        ]);
        $member = $stmt->fetch();
        if (!$member) {
            http_response_code(404);
            echo '404';
            return;
        }

        $error = Session::flash('error');
        $flash = Session::flash('success');
        require base_path('views/members/edit.php');
    }

    public static function update(): void
    {
        Access::require('members', 'write');

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo '400';
            return;
        }

        $tenantId = (int)$_SESSION['tenant_id'];

        $first = trim((string)($_POST['first_name'] ?? ''));
        $last = trim((string)($_POST['last_name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $status = (string)($_POST['status'] ?? 'active');
        $memberSince = trim((string)($_POST['member_since'] ?? ''));
        $paidUntil = trim((string)($_POST['membership_paid_until'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            Session::flash('error', 'Email invalide.');
            redirect('/members/edit?id=' . $id);
        }

        if ($memberSince !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $memberSince)) {
            Session::flash('error', 'Date d\'adhésion invalide.');
            redirect('/members/edit?id=' . $id);
        }

        if ($paidUntil !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $paidUntil)) {
            Session::flash('error', 'Date de cotisation invalide.');
            redirect('/members/edit?id=' . $id);
        }

        $pdo = Db::pdo();
        try {
            $stmt = $pdo->prepare(
                'UPDATE members
                 SET first_name = :first_name,
                     last_name = :last_name,
                     email = :email,
                     phone = :phone,
                     status = :status,
                     member_since = :member_since,
                     membership_paid_until = :membership_paid_until,
                     notes = :notes
                 WHERE id = :id AND tenant_id = :tenant_id'
            );
            $stmt->execute([
                'first_name' => ($first !== '' ? $first : null),
                'last_name' => ($last !== '' ? $last : null),
                'email' => ($email !== '' ? $email : null),
                'phone' => ($phone !== '' ? $phone : null),
                'status' => $status,
                'member_since' => ($memberSince !== '' ? $memberSince : null),
                'membership_paid_until' => ($paidUntil !== '' ? $paidUntil : null),
                'notes' => ($notes !== '' ? $notes : null),
                'id' => $id,
                'tenant_id' => $tenantId,
            ]);
        } catch (\Throwable $e) {
            Session::flash('error', 'Erreur lors de la mise à jour (email déjà utilisé ?).');
            redirect('/members/edit?id=' . $id);
        }

        Session::flash('success', 'Adhérent mis à jour.');
        redirect('/members/edit?id=' . $id);
    }
}
