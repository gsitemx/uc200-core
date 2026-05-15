<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Session;
use App\Models\User;
use PDO;

final class AuthService
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function attempt(string $email, string $password): bool
    {
        $user = User::findByEmail($this->db, $email);

        if ($user === null || ! password_verify($password, $user['password_hash'])) {
            return false;
        }

        if ((int) $user['is_active'] !== 1) {
            return false;
        }

        $roles = User::roles($this->db, (int) $user['id']);

        Session::regenerate();
        Session::put('user_id', (int) $user['id']);
        Session::put('company_id', $user['company_id'] !== null ? (int) $user['company_id'] : null);
        Session::put('company_uuid', $user['company_uuid'] ?? null);
        Session::put('company_name', $user['company_name'] ?? null);
        Session::put('company_status', $user['company_status'] ?? null);
        Session::put('user_name', $user['name']);
        Session::put('user_email', $user['email']);
        Session::put('user_roles', $roles);

        $statement = $this->db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $statement->execute(['id' => (int) $user['id']]);

        return true;
    }

    public function logout(): void
    {
        Session::destroy();
    }
}
