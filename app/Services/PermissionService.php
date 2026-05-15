<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class PermissionService
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function userHasPermission(int $userId, string $permission): bool
    {
        $statement = $this->db->prepare(
            'SELECT COUNT(*) FROM user_roles ur
             INNER JOIN role_permissions rp ON rp.role_id = ur.role_id
             INNER JOIN permissions p ON p.id = rp.permission_id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = :user_id
               AND p.slug = :permission
               AND p.deleted_at IS NULL
               AND r.deleted_at IS NULL'
        );
        $statement->execute([
            'user_id' => $userId,
            'permission' => $permission,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }
}
