<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use PDO;

final class ApiTokenService
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function create(int $userId, ?int $companyId, string $name, array $scopes, ?string $expiresAt, string $allowedIps = ''): array
    {
        $plain = 'uc200_pat_' . bin2hex(random_bytes(32));
        $statement = $this->db->prepare(
            'INSERT INTO api_tokens (uuid, user_id, company_id, name, token_hash, token_prefix, last_four, scopes, allowed_ips, expires_at)
             VALUES (:uuid, :user_id, :company_id, :name, :token_hash, :token_prefix, :last_four, :scopes, :allowed_ips, :expires_at)'
        );
        $uuid = uuid();
        $statement->execute([
            'uuid' => $uuid,
            'user_id' => $userId,
            'company_id' => $companyId,
            'name' => $name,
            'token_hash' => hash('sha256', $plain),
            'token_prefix' => substr($plain, 0, 12),
            'last_four' => substr($plain, -4),
            'scopes' => json_encode(array_values(array_unique($scopes)), JSON_THROW_ON_ERROR),
            'allowed_ips' => trim($allowedIps) !== '' ? trim($allowedIps) : null,
            'expires_at' => $expiresAt !== '' ? $expiresAt : null,
        ]);

        return [
            'uuid' => $uuid,
            'token' => $plain,
            'last_four' => substr($plain, -4),
        ];
    }

    public function authenticate(Request $request): ?array
    {
        $plain = $request->bearerToken();

        if ($plain === null || $plain === '') {
            return null;
        }

        $statement = $this->db->prepare(
            'SELECT t.*, u.name AS user_name, u.email AS user_email, u.is_active, c.uuid AS company_uuid, c.name AS company_name
             FROM api_tokens t
             INNER JOIN users u ON u.id = t.user_id AND u.deleted_at IS NULL
             LEFT JOIN companies c ON c.id = t.company_id AND c.deleted_at IS NULL
             WHERE t.token_hash = :hash
               AND t.status = "active"
               AND t.deleted_at IS NULL
               AND (t.expires_at IS NULL OR t.expires_at > NOW())
             LIMIT 1'
        );
        $statement->execute(['hash' => hash('sha256', $plain)]);
        $token = $statement->fetch();

        if ($token === false || (int) $token['is_active'] !== 1 || ! $this->ipAllowed((string) ($token['allowed_ips'] ?? ''), (string) $request->ip())) {
            return null;
        }

        $this->db->prepare('UPDATE api_tokens SET last_used_at = NOW(), last_used_ip = :ip WHERE id = :id')
            ->execute(['ip' => $request->ip(), 'id' => (int) $token['id']]);

        $token['scopes_array'] = $this->decodeScopes((string) $token['scopes']);

        return $token;
    }

    public function can(array $token, string $scope): bool
    {
        $scopes = $token['scopes_array'] ?? $this->decodeScopes((string) ($token['scopes'] ?? '[]'));

        return in_array('*', $scopes, true)
            || in_array($scope, $scopes, true)
            || in_array(strtok($scope, ':') . ':*', $scopes, true);
    }

    public function revoke(string $uuid, int $userId, bool $superAdmin = false): bool
    {
        $sql = 'UPDATE api_tokens SET status = "revoked", revoked_at = NOW(), deleted_at = NOW() WHERE uuid = :uuid';
        $params = ['uuid' => $uuid];

        if (! $superAdmin) {
            $sql .= ' AND user_id = :user_id';
            $params['user_id'] = $userId;
        }

        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        return $statement->rowCount() > 0;
    }

    public function tokensForUser(int $userId, bool $superAdmin = false): array
    {
        $sql =
            'SELECT t.*, u.email AS user_email, c.name AS company_name
             FROM api_tokens t
             INNER JOIN users u ON u.id = t.user_id
             LEFT JOIN companies c ON c.id = t.company_id
             WHERE t.deleted_at IS NULL';
        $params = [];

        if (! $superAdmin) {
            $sql .= ' AND t.user_id = :user_id';
            $params['user_id'] = $userId;
        }

        $sql .= ' ORDER BY t.created_at DESC';
        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function hitRateLimit(string $key, int $limit = 120, int $windowSeconds = 60): bool
    {
        $windowStart = gmdate('Y-m-d H:i:s', time() - $windowSeconds);
        $this->db->prepare('DELETE FROM api_rate_limits WHERE window_started_at < :window_start')->execute(['window_start' => $windowStart]);
        $hash = hash('sha256', $key);

        $statement = $this->db->prepare(
            'INSERT INTO api_rate_limits (rate_key_hash, attempts, window_started_at)
             VALUES (:hash, 1, NOW())
             ON DUPLICATE KEY UPDATE attempts = attempts + 1'
        );
        $statement->execute(['hash' => $hash]);

        $check = $this->db->prepare('SELECT attempts FROM api_rate_limits WHERE rate_key_hash = :hash');
        $check->execute(['hash' => $hash]);

        return (int) $check->fetchColumn() > $limit;
    }

    private function ipAllowed(string $allowedIps, string $ip): bool
    {
        if (trim($allowedIps) === '') {
            return true;
        }

        $allowed = array_filter(array_map('trim', preg_split('/[\s,]+/', $allowedIps) ?: []));

        return in_array($ip, $allowed, true);
    }

    private function decodeScopes(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? array_values(array_filter(array_map('strval', $decoded))) : [];
    }
}
