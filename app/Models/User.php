<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class User
{
    public static function findByEmail(PDO $db, string $email): ?array
    {
        $statement = $db->prepare(
            'SELECT users.*, companies.uuid AS company_uuid, companies.name AS company_name, companies.status AS company_status
             FROM users
             LEFT JOIN companies ON companies.id = users.company_id AND companies.deleted_at IS NULL
             WHERE users.email = :email AND users.deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        return $user === false ? null : $user;
    }

    public static function roles(PDO $db, int $userId): array
    {
        $statement = $db->prepare(
            'SELECT r.slug
             FROM user_roles ur
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = :user_id AND r.deleted_at IS NULL'
        );
        $statement->execute(['user_id' => $userId]);

        return array_column($statement->fetchAll(), 'slug');
    }
}
