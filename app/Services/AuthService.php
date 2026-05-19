<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Session;
use App\Models\User;
use PDO;
use Throwable;

final class AuthService
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function attempt(string $identifier, string $password): bool
    {
        $identifier = trim($identifier);
        $user = User::findByLoginIdentifier($this->db, $identifier);

        if ($user === null || ! password_verify($password, $user['password_hash'])) {
            $this->auditLogin('auth.login_failed', $identifier, $user);
            return false;
        }

        if ((int) $user['is_active'] !== 1) {
            $this->auditLogin('auth.login_failed_inactive', $identifier, $user);
            return false;
        }

        $roles = User::roles($this->db, (int) $user['id']);

        Session::regenerate();
        Session::put('user_id', (int) $user['id']);
        Session::put('company_id', $user['company_id'] !== null ? (int) $user['company_id'] : null);
        Session::put('company_uuid', $user['company_uuid'] ?? null);
        Session::put('company_name', $user['company_name'] ?? null);
        Session::put('company_status', $user['company_status'] ?? null);
        Session::put('company_locale', $this->normalizeLocale($user['company_locale'] ?? null) ?? 'es');
        Session::put('user_name', $user['name']);
        Session::put('user_email', $user['email']);
        Session::put('user_locale', $this->normalizeLocale($user['locale'] ?? null));
        Session::put('user_roles', $roles);

        $statement = $this->db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $statement->execute(['id' => (int) $user['id']]);
        $this->auditLogin('auth.login_success', $identifier, $user);

        return true;
    }

    public function logout(): void
    {
        Session::destroy();
    }

    private function normalizeLocale(?string $locale): ?string
    {
        $locale = strtolower(substr((string) $locale, 0, 2));

        return in_array($locale, ['es', 'en'], true) ? $locale : null;
    }

    private function auditLogin(string $action, string $identifier, ?array $user): void
    {
        try {
            (new AuditService($this->db))->record($action, 'users', isset($user['id']) ? (int) $user['id'] : null, [
                'identifier_type' => $this->identifierType($identifier),
                'identifier_hash' => hash('sha256', strtolower($identifier)),
                'company_id' => $user['company_id'] ?? null,
            ], isset($user['company_id']) ? (int) $user['company_id'] : null);
        } catch (Throwable) {
            // Login must not fail if audit storage is temporarily unavailable.
        }
    }

    private function identifierType(string $identifier): string
    {
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }

        if (preg_match('/^[0-9]{2,12}$/', $identifier) === 1) {
            return 'extension';
        }

        return 'username';
    }
}
