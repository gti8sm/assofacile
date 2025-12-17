<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Db;
use App\Support\Access;
use App\Support\MedicalAccess;
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
        $birthDate = trim((string)($_POST['birth_date'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $memberSince = trim((string)($_POST['member_since'] ?? ''));
        $paidUntil = trim((string)($_POST['membership_paid_until'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            Session::flash('error', 'Email invalide.');
            redirect('/members/new');
        }

        if ($memberSince !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $memberSince)) {
            Session::flash('error', 'Date d\'adhésion invalide.');
            redirect('/members/new');
        }

        if ($birthDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDate)) {
            Session::flash('error', 'Date de naissance invalide.');
            redirect('/members/new');
        }

        if ($paidUntil !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $paidUntil)) {
            Session::flash('error', 'Date de cotisation invalide.');
            redirect('/members/new');
        }

        $pdo = Db::pdo();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO members (tenant_id, first_name, last_name, birth_date, email, phone, address, status, member_since, membership_paid_until, notes)
                 VALUES (:tenant_id, :first_name, :last_name, :birth_date, :email, :phone, :address, :status, :member_since, :membership_paid_until, :notes)'
            );
            $stmt->execute([
                'tenant_id' => $tenantId,
                'first_name' => ($first !== '' ? $first : null),
                'last_name' => ($last !== '' ? $last : null),
                'birth_date' => ($birthDate !== '' ? $birthDate : null),
                'email' => ($email !== '' ? $email : null),
                'phone' => ($phone !== '' ? $phone : null),
                'address' => ($address !== '' ? $address : null),
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

        $tenantId = (int)$_SESSION['tenant_id'];
        $userId = (int)$_SESSION['user_id'];
        $canMedical = MedicalAccess::can($tenantId, $userId, (int)$member['id']);

        $stmt = $pdo->prepare('SELECT id, name FROM households WHERE tenant_id = :tenant_id ORDER BY id DESC');
        $stmt->execute(['tenant_id' => $tenantId]);
        $households = $stmt->fetchAll();

        $medical = null;
        if ($canMedical) {
            $stmt = $pdo->prepare('SELECT allergies, medical_notes FROM member_medical_profiles WHERE member_id = :member_id LIMIT 1');
            $stmt->execute(['member_id' => (int)$member['id']]);
            $medical = $stmt->fetch();
        }

        $pickups = [];
        if ($canMedical && (string)($member['relationship'] ?? 'adult') === 'child') {
            $stmt = $pdo->prepare(
                'SELECT id, name, phone, relation, notes
                 FROM member_authorized_pickups
                 WHERE member_id = :member_id
                 ORDER BY id DESC'
            );
            $stmt->execute(['member_id' => (int)$member['id']]);
            $pickups = $stmt->fetchAll();
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
        $birthDate = trim((string)($_POST['birth_date'] ?? ''));
        $householdId = (int)($_POST['household_id'] ?? 0);
        $relationship = (string)($_POST['relationship'] ?? 'adult');
        $useHouseholdAddress = isset($_POST['use_household_address']) ? 1 : 0;
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $status = (string)($_POST['status'] ?? 'active');
        $memberSince = trim((string)($_POST['member_since'] ?? ''));
        $paidUntil = trim((string)($_POST['membership_paid_until'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        $allergies = trim((string)($_POST['medical_allergies'] ?? ''));
        $medicalNotes = trim((string)($_POST['medical_notes'] ?? ''));

        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }

        if (!in_array($relationship, ['adult', 'spouse', 'child'], true)) {
            $relationship = 'adult';
        }

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            Session::flash('error', 'Email invalide.');
            redirect('/members/edit?id=' . $id);
        }

        if ($memberSince !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $memberSince)) {
            Session::flash('error', 'Date d\'adhésion invalide.');
            redirect('/members/edit?id=' . $id);
        }

        if ($birthDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDate)) {
            Session::flash('error', 'Date de naissance invalide.');
            redirect('/members/edit?id=' . $id);
        }

        if ($paidUntil !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $paidUntil)) {
            Session::flash('error', 'Date de cotisation invalide.');
            redirect('/members/edit?id=' . $id);
        }

        $pdo = Db::pdo();
        try {
            $pdo->beginTransaction();

            if ($householdId > 0) {
                $stmt = $pdo->prepare('SELECT id FROM households WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
                $stmt->execute([
                    'id' => $householdId,
                    'tenant_id' => $tenantId,
                ]);
                if (!$stmt->fetch()) {
                    $householdId = 0;
                }
            }

            $stmt = $pdo->prepare(
                'UPDATE members
                 SET first_name = :first_name,
                     last_name = :last_name,
                     birth_date = :birth_date,
                     household_id = :household_id,
                     relationship = :relationship,
                     use_household_address = :use_household_address,
                     email = :email,
                     phone = :phone,
                     address = :address,
                     status = :status,
                     member_since = :member_since,
                     membership_paid_until = :membership_paid_until,
                     notes = :notes
                 WHERE id = :id AND tenant_id = :tenant_id'
            );
            $stmt->execute([
                'first_name' => ($first !== '' ? $first : null),
                'last_name' => ($last !== '' ? $last : null),
                'birth_date' => ($birthDate !== '' ? $birthDate : null),
                'household_id' => ($householdId > 0 ? $householdId : null),
                'relationship' => $relationship,
                'use_household_address' => $useHouseholdAddress,
                'email' => ($email !== '' ? $email : null),
                'phone' => ($phone !== '' ? $phone : null),
                'address' => ($address !== '' ? $address : null),
                'status' => $status,
                'member_since' => ($memberSince !== '' ? $memberSince : null),
                'membership_paid_until' => ($paidUntil !== '' ? $paidUntil : null),
                'notes' => ($notes !== '' ? $notes : null),
                'id' => $id,
                'tenant_id' => $tenantId,
            ]);

            if (MedicalAccess::can($tenantId, (int)$_SESSION['user_id'], $id)) {
                $stmt = $pdo->prepare(
                    'INSERT INTO member_medical_profiles (member_id, allergies, medical_notes)
                     VALUES (:member_id, :allergies, :medical_notes)
                     ON DUPLICATE KEY UPDATE
                       allergies = VALUES(allergies),
                       medical_notes = VALUES(medical_notes)'
                );
                $stmt->execute([
                    'member_id' => $id,
                    'allergies' => ($allergies !== '' ? $allergies : null),
                    'medical_notes' => ($medicalNotes !== '' ? $medicalNotes : null),
                ]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Session::flash('error', 'Erreur lors de la mise à jour (email déjà utilisé ?).');
            redirect('/members/edit?id=' . $id);
        }

        Session::flash('success', 'Adhérent mis à jour.');
        redirect('/members/edit?id=' . $id);
    }
}
