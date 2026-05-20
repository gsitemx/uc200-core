<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class AmiSettingsService
{
    private const PREFIX = 'pbx.ami.';

    private readonly CryptoService $crypto;

    public function __construct(
        private readonly PDO $db,
        ?CryptoService $crypto = null,
    ) {
        $this->crypto = $crypto ?? new CryptoService();
    }

    public function forCompany(?int $companyId, bool $includeSecret = false): array
    {
        $settings = $this->defaults();

        foreach ($this->fetchRows(null) as $key => $value) {
            $settings[$key] = $value;
        }

        if ($companyId !== null && $companyId > 0) {
            foreach ($this->fetchRows($companyId) as $key => $value) {
                $settings[$key] = $value;
            }
        }

        $password = $this->crypto->decrypt((string) ($settings['password_encrypted'] ?? ''));
        $settings['password_configured'] = $password !== '';
        $settings['password'] = $includeSecret ? $password : '';
        $settings['scope_company_id'] = $companyId;
        $settings['secure_storage'] = $this->crypto->strongKeyConfigured();

        return $settings;
    }

    public function save(?int $companyId, array $input): void
    {
        $current = $this->forCompany($companyId, true);
        $payload = [
            'enabled' => in_array((string) ($input['enabled'] ?? 'no'), ['1', 'yes', 'true', 'on'], true) ? 'yes' : 'no',
            'host' => substr(trim((string) ($input['host'] ?? $current['host'])), 0, 160) ?: '127.0.0.1',
            'port' => max(1, min(65535, (int) ($input['port'] ?? $current['port']))),
            'username' => substr(trim((string) ($input['username'] ?? $current['username'])), 0, 160),
            'connect_timeout' => max(1, min(20, (int) ($input['connect_timeout'] ?? $current['connect_timeout']))),
            'originate_context' => substr(trim((string) ($input['originate_context'] ?? $current['originate_context'])), 0, 80),
        ];

        $password = trim((string) ($input['password'] ?? ''));
        $payload['password_encrypted'] = $password !== ''
            ? $this->crypto->encrypt($password)
            : (string) ($current['password_encrypted'] ?? '');

        foreach ($payload as $key => $value) {
            $this->upsert($companyId, self::PREFIX . $key, $value);
        }
    }

    public function defaults(): array
    {
        return [
            'enabled' => 'no',
            'host' => '127.0.0.1',
            'port' => 5038,
            'username' => 'admin',
            'password_encrypted' => '',
            'connect_timeout' => 3,
            'originate_context' => '',
        ];
    }

    private function fetchRows(?int $companyId): array
    {
        if ($companyId !== null && $companyId > 0) {
            $statement = $this->db->prepare(
                'SELECT setting_key, setting_value
                 FROM settings
                 WHERE company_id = :company_id
                   AND deleted_at IS NULL
                   AND setting_key LIKE :prefix
                 ORDER BY setting_key'
            );
            $statement->execute([
                'company_id' => $companyId,
                'prefix' => self::PREFIX . '%',
            ]);
        } else {
            $statement = $this->db->prepare(
                'SELECT setting_key, setting_value
                 FROM settings
                 WHERE company_id IS NULL
                   AND deleted_at IS NULL
                   AND setting_key LIKE :prefix
                 ORDER BY setting_key'
            );
            $statement->execute([
                'prefix' => self::PREFIX . '%',
            ]);
        }

        $rows = [];
        foreach ($statement->fetchAll() as $row) {
            $key = substr((string) $row['setting_key'], strlen(self::PREFIX));
            $rows[$key] = json_decode((string) $row['setting_value'], true);
        }

        return $rows;
    }

    private function upsert(?int $companyId, string $key, mixed $value): void
    {
        $json = json_encode($value, JSON_THROW_ON_ERROR);

        if ($companyId !== null && $companyId > 0) {
            $statement = $this->db->prepare(
                'INSERT INTO settings (uuid, company_id, setting_key, setting_value, is_public)
                 VALUES (:uuid, :company_id, :setting_key, :setting_value, 0)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), deleted_at = NULL'
            );
            $statement->execute([
                'uuid' => uuid(),
                'company_id' => $companyId,
                'setting_key' => $key,
                'setting_value' => $json,
            ]);
            return;
        }

        $existing = $this->db->prepare(
            'SELECT id FROM settings WHERE company_id IS NULL AND setting_key = :setting_key LIMIT 1'
        );
        $existing->execute(['setting_key' => $key]);
        $id = $existing->fetchColumn();

        if ($id !== false) {
            $update = $this->db->prepare(
                'UPDATE settings SET setting_value = :setting_value, deleted_at = NULL WHERE id = :id'
            );
            $update->execute(['setting_value' => $json, 'id' => (int) $id]);
            return;
        }

        $insert = $this->db->prepare(
            'INSERT INTO settings (uuid, company_id, setting_key, setting_value, is_public)
             VALUES (:uuid, NULL, :setting_key, :setting_value, 0)'
        );
        $insert->execute([
            'uuid' => uuid(),
            'setting_key' => $key,
            'setting_value' => $json,
        ]);
    }
}
