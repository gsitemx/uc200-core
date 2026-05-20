<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;

final class MenuService
{
    private array $permissionCache = [];
    private array $featureCache = [];
    private array $tableCache = [];
    private ?array $badgeCache = null;

    public function __construct(private readonly PDO $db)
    {
    }

    public function build(array $user): array
    {
        $sections = require base_path('config/menu.php');
        $resolved = [];

        foreach ($sections as $section) {
            $items = [];

            foreach (($section['items'] ?? []) as $item) {
                if (! $this->isVisible($item, $user)) {
                    continue;
                }

                $item['label'] = $this->translate($item);
                $item['active'] = is_active_path((string) ($item['path'] ?? '#'));
                $item['badge_value'] = $this->badgeValue($item['badge'] ?? null, $user);
                $item['badge_tone'] = $item['badge_tone'] ?? null;
                $item['search'] = $this->searchIndex($section, $item);
                $items[] = $item;
            }

            if ($items === []) {
                continue;
            }

            $section['label'] = $this->translate($section);
            $section['active'] = array_reduce($items, static fn (bool $carry, array $item): bool => $carry || ! empty($item['active']), false);
            $section['badge_value'] = $this->badgeValue($section['badge'] ?? null, $user);
            $section['badge_tone'] = $section['badge_tone'] ?? null;
            $section['items'] = $items;
            $resolved[] = $section;
        }

        return $resolved;
    }

    private function isVisible(array $item, array $user): bool
    {
        $path = (string) ($item['path'] ?? '');
        $isSuperAdmin = in_array('super-admin', $user['roles'] ?? [], true);

        if ($path === '' || $path === '#') {
            return false;
        }

        if (($item['company_only'] ?? false) && ! $isSuperAdmin && empty($user['company_id'])) {
            return false;
        }

        if (! empty($item['roles']) && count(array_intersect($item['roles'], $user['roles'] ?? [])) === 0) {
            return false;
        }

        $permission = $item['permission'] ?? null;
        if (is_string($permission) && $permission !== '' && ! $this->hasPermission($user, $permission)) {
            return false;
        }

        $feature = $item['feature'] ?? null;
        if (is_string($feature) && $feature !== '' && ! $this->hasFeature($user, $feature)) {
            return false;
        }

        return true;
    }

    private function hasPermission(array $user, string $permission): bool
    {
        if (in_array('super-admin', $user['roles'] ?? [], true)) {
            return true;
        }

        $userId = (int) ($user['id'] ?? 0);
        if ($userId <= 0) {
            return false;
        }

        if (! array_key_exists($permission, $this->permissionCache)) {
            $this->permissionCache[$permission] = (new PermissionService($this->db))->userHasPermission($userId, $permission);
        }

        return $this->permissionCache[$permission];
    }

    private function hasFeature(array $user, string $feature): bool
    {
        if (in_array('super-admin', $user['roles'] ?? [], true)) {
            return true;
        }

        $companyId = isset($user['company_id']) ? (int) $user['company_id'] : 0;
        if ($companyId <= 0) {
            return false;
        }

        $cacheKey = $companyId . ':' . $feature;
        if (! array_key_exists($cacheKey, $this->featureCache)) {
            $this->featureCache[$cacheKey] = (new LicenseService($this->db))->hasFeature($companyId, $feature);
        }

        return $this->featureCache[$cacheKey];
    }

    private function badgeValue(?string $key, array $user): ?int
    {
        if (! is_string($key) || $key === '') {
            return null;
        }

        $badges = $this->badges($user);
        $value = $badges[$key] ?? null;

        if ($value === null) {
            return null;
        }

        return $value > 0 ? $value : null;
    }

    private function badges(array $user): array
    {
        if ($this->badgeCache !== null) {
            return $this->badgeCache;
        }

        $companyId = isset($user['company_id']) ? (int) $user['company_id'] : null;
        $isSuperAdmin = in_array('super-admin', $user['roles'] ?? [], true);
        $isReseller = in_array('reseller', $user['roles'] ?? [], true);

        $securityWindow = 5;
        if ($this->tableExists('security_settings')) {
            $securityWindow = max(1, (int) ($this->settingValue('security.monitor.window_minutes') ?? 5));
        }

        $this->badgeCache = [
            'security_attacks' => $this->tableExists('security_events')
                ? $this->countScoped(
                    'security_events',
                    'detected_at >= (NOW() - INTERVAL ' . $securityWindow . ' MINUTE)
                     AND deleted_at IS NULL
                     AND (severity = "critical" OR event_type IN ("failed_auth", "scanner", "register_flood", "multi_extension"))',
                    [],
                    $companyId,
                    $isSuperAdmin
                )
                : 0,
            'extensions_online' => $this->tableExists('ps_endpoints')
                ? $this->countScoped(
                    'ps_endpoints',
                    'deleted_at IS NULL
                     AND status = "active"
                     AND (sip_status IN ("registered", "reachable") OR presence_status IN ("online", "busy", "away"))',
                    [],
                    $companyId,
                    $isSuperAdmin
                )
                : 0,
            'expired_licenses' => $this->tableExists('company_licenses')
                ? ($isReseller && ! $isSuperAdmin ? 0 : $this->safeCount(
                    'SELECT COUNT(*)
                     FROM company_licenses
                     WHERE deleted_at IS NULL
                       AND (status = "expired" OR (expires_at IS NOT NULL AND expires_at < NOW()))'
                ))
                : 0,
            'pending_invoices' => $this->tableExists('billing_invoices')
                ? ($isReseller && ! $isSuperAdmin && $companyId === null
                    ? 0
                    : $this->countScoped(
                        'billing_invoices',
                        'deleted_at IS NULL
                         AND status IN ("draft", "open", "pending", "past_due")',
                        [],
                        $companyId,
                        $isSuperAdmin
                    ))
                : 0,
        ];

        return $this->badgeCache;
    }

    private function countScoped(string $table, string $where, array $params, ?int $companyId, bool $isSuperAdmin): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $where;
        $bindings = $params;

        if (! $isSuperAdmin && $companyId !== null) {
            $sql .= ' AND company_id = :company_id';
            $bindings['company_id'] = $companyId;
        }

        return $this->safeCount($sql, $bindings);
    }

    private function safeCount(string $sql, array $params = []): int
    {
        try {
            $statement = $this->db->prepare($sql);
            $statement->execute($params);

            return (int) $statement->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    private function settingValue(string $key): ?string
    {
        try {
            $statement = $this->db->prepare(
                'SELECT setting_value
                 FROM security_settings
                 WHERE company_id IS NULL
                   AND setting_key = :setting_key
                   AND deleted_at IS NULL
                 ORDER BY id DESC
                 LIMIT 1'
            );
            $statement->execute(['setting_key' => $key]);
            $value = $statement->fetchColumn();

            return $value === false ? null : (string) $value;
        } catch (Throwable) {
            return null;
        }
    }

    private function tableExists(string $table): bool
    {
        if (array_key_exists($table, $this->tableCache)) {
            return $this->tableCache[$table];
        }

        try {
            $statement = $this->db->prepare(
                'SELECT COUNT(*)
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table_name'
            );
            $statement->execute(['table_name' => $table]);
            $this->tableCache[$table] = (int) $statement->fetchColumn() > 0;
        } catch (Throwable) {
            $this->tableCache[$table] = false;
        }

        return $this->tableCache[$table];
    }

    private function translate(array $node): string
    {
        if (! empty($node['label_key'])) {
            return __((string) $node['label_key']);
        }

        return (string) ($node['label'] ?? '');
    }

    private function searchIndex(array $section, array $item): string
    {
        $parts = [
            $this->translate($section),
            $this->translate($item),
        ];

        foreach ((array) ($item['keywords'] ?? []) as $keyword) {
            $parts[] = (string) $keyword;
        }

        return strtolower(implode(' ', array_filter($parts)));
    }
}
