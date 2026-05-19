<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use PDOException;

final class User
{
    public static function findByEmail(PDO $db, string $email): ?array
    {
        return self::findByColumn($db, 'users.email = :identifier', ['identifier' => $email]);
    }

    public static function findByLoginIdentifier(PDO $db, string $identifier): ?array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return self::findByEmail($db, $identifier);
        }

        $user = self::findByUsername($db, $identifier);
        if ($user !== null) {
            return $user;
        }

        if (preg_match('/^[0-9]{2,12}$/', $identifier) === 1) {
            return self::findByExtensionNumber($db, $identifier);
        }

        return null;
    }

    public static function findByUsername(PDO $db, string $username): ?array
    {
        try {
            return self::findByColumn($db, 'users.username = :identifier', ['identifier' => $username]);
        } catch (PDOException) {
            return null;
        }
    }

    public static function findByExtensionNumber(PDO $db, string $extension): ?array
    {
        $select = self::selectWithCompany();
        $sql =
            $select . '
             FROM users
             INNER JOIN ps_endpoints e ON e.company_id = users.company_id
                AND e.deleted_at IS NULL
                AND e.status = "active"
                AND e.extension_number = :extension
                AND (e.email = users.email OR e.contact_email = users.email)
             LEFT JOIN companies ON companies.id = users.company_id AND companies.deleted_at IS NULL
             WHERE users.deleted_at IS NULL
               AND users.is_active = 1
             LIMIT 2';

        try {
            $statement = $db->prepare($sql);
        } catch (PDOException) {
            $sql =
                self::selectWithCompany(false) . '
                 FROM users
                 INNER JOIN ps_endpoints e ON e.company_id = users.company_id
                    AND e.deleted_at IS NULL
                    AND e.status = "active"
                    AND e.extension_number = :extension
                    AND e.contact_email = users.email
                 LEFT JOIN companies ON companies.id = users.company_id AND companies.deleted_at IS NULL
                 WHERE users.deleted_at IS NULL
                   AND users.is_active = 1
                 LIMIT 2';
            $statement = $db->prepare($sql);
        }

        $statement->execute(['extension' => $extension]);
        $users = $statement->fetchAll();

        return count($users) === 1 ? $users[0] : null;
    }

    private static function findByColumn(PDO $db, string $where, array $params): ?array
    {
        try {
            $statement = $db->prepare(
                self::selectWithCompany() . '
                 FROM users
                 LEFT JOIN companies ON companies.id = users.company_id AND companies.deleted_at IS NULL
                 WHERE ' . $where . ' AND users.deleted_at IS NULL
                 LIMIT 1'
            );
        } catch (PDOException $exception) {
            $statement = $db->prepare(
                self::selectWithCompany(false) . '
                 FROM users
                 LEFT JOIN companies ON companies.id = users.company_id AND companies.deleted_at IS NULL
                 WHERE ' . $where . ' AND users.deleted_at IS NULL
                 LIMIT 1'
            );
        }
        $statement->execute($params);
        $user = $statement->fetch();

        return $user === false ? null : $user;
    }

    private static function selectWithCompany(bool $companyLocale = true): string
    {
        return 'SELECT users.*, companies.uuid AS company_uuid, companies.name AS company_name,
                companies.status AS company_status, ' . ($companyLocale ? 'companies.locale' : 'NULL') . ' AS company_locale';
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
