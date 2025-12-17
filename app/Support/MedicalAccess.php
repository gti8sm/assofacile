<?php

declare(strict_types=1);

namespace App\Support;

use App\Database\Db;

final class MedicalAccess
{
    public static function can(int $tenantId, int $userId, int $memberId): bool
    {
        if ($tenantId <= 0 || $userId <= 0 || $memberId <= 0) {
            return false;
        }

        if (isset($_SESSION['is_admin']) && (int)$_SESSION['is_admin'] === 1) {
            return true;
        }

        if (isset($_SESSION['role']) && (string)$_SESSION['role'] === 'manager') {
            return true;
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare(
            'SELECT 1
             FROM child_group_members cgm
             INNER JOIN child_group_staff cgs ON cgs.group_id = cgm.group_id
             INNER JOIN child_groups cg ON cg.id = cgm.group_id
             INNER JOIN members m ON m.id = cgm.member_id
             WHERE cg.tenant_id = :tenant_id
               AND cgs.user_id = :user_id
               AND m.tenant_id = :tenant_id
               AND m.id = :member_id
             LIMIT 1'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'member_id' => $memberId,
        ]);

        return (bool)$stmt->fetch();
    }
}
