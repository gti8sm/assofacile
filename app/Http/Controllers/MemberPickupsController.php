<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Db;
use App\Support\Access;
use App\Support\MedicalAccess;
use App\Support\Session;

final class MemberPickupsController
{
    public static function store(): void
    {
        Access::require('members', 'read');

        $tenantId = (int)$_SESSION['tenant_id'];
        $userId = (int)$_SESSION['user_id'];

        $memberId = (int)($_POST['member_id'] ?? 0);
        if ($memberId <= 0) {
            http_response_code(400);
            echo '400';
            return;
        }

        if (!MedicalAccess::can($tenantId, $userId, $memberId)) {
            http_response_code(403);
            echo '403';
            return;
        }

        $name = trim((string)($_POST['name'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $relation = trim((string)($_POST['relation'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        if ($name === '') {
            Session::flash('error', 'Nom requis.');
            redirect('/members/edit?id=' . $memberId);
        }

        $pdo = Db::pdo();

        $stmt = $pdo->prepare('SELECT id, relationship FROM members WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
        $stmt->execute([
            'id' => $memberId,
            'tenant_id' => $tenantId,
        ]);
        $member = $stmt->fetch();
        if (!$member) {
            http_response_code(404);
            echo '404';
            return;
        }

        if ((string)($member['relationship'] ?? 'adult') !== 'child') {
            Session::flash('error', 'Cette section est réservée aux enfants.');
            redirect('/members/edit?id=' . $memberId);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO member_authorized_pickups (member_id, name, phone, relation, notes)
             VALUES (:member_id, :name, :phone, :relation, :notes)'
        );
        $stmt->execute([
            'member_id' => $memberId,
            'name' => $name,
            'phone' => ($phone !== '' ? $phone : null),
            'relation' => ($relation !== '' ? $relation : null),
            'notes' => ($notes !== '' ? $notes : null),
        ]);

        Session::flash('success', 'Personne habilitée ajoutée.');
        redirect('/members/edit?id=' . $memberId);
    }

    public static function delete(): void
    {
        Access::require('members', 'read');

        $tenantId = (int)$_SESSION['tenant_id'];
        $userId = (int)$_SESSION['user_id'];

        $memberId = (int)($_POST['member_id'] ?? 0);
        $id = (int)($_POST['id'] ?? 0);
        if ($memberId <= 0 || $id <= 0) {
            http_response_code(400);
            echo '400';
            return;
        }

        if (!MedicalAccess::can($tenantId, $userId, $memberId)) {
            http_response_code(403);
            echo '403';
            return;
        }

        $pdo = Db::pdo();

        $stmt = $pdo->prepare(
            'DELETE map
             FROM member_authorized_pickups map
             INNER JOIN members m ON m.id = map.member_id
             WHERE map.id = :id AND map.member_id = :member_id AND m.tenant_id = :tenant_id'
        );
        $stmt->execute([
            'id' => $id,
            'member_id' => $memberId,
            'tenant_id' => $tenantId,
        ]);

        Session::flash('success', 'Personne supprimée.');
        redirect('/members/edit?id=' . $memberId);
    }
}
