<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class RoleService
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function rolesForUser(int $userId): array
    {
        $statement = $this->db->prepare(
            'SELECT r.slug
             FROM user_roles ur
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = :user_id AND r.deleted_at IS NULL'
        );
        $statement->execute(['user_id' => $userId]);

        return array_column($statement->fetchAll(), 'slug');
    }

    public function userHasAnyRole(int $userId, array $roles): bool
    {
        $userRoles = $this->rolesForUser($userId);

        return count(array_intersect($roles, $userRoles)) > 0;
    }
}
