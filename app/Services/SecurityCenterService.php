<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class SecurityCenterService
{
    private const DEFAULT_JAIL = 'uc200-asterisk';

    public function __construct(private readonly PDO $db)
    {
    }

    public function ready(): bool
    {
        return $this->tableExists('security_settings')
            && $this->tableExists('security_events')
            && $this->tableExists('security_bans')
            && $this->tableExists('security_ip_whitelist')
            && $this->tableExists('security_ip_blacklist');
    }

    public function dashboard(?int $companyId): array
    {
        $this->syncSecurityEvents($companyId);
        $events = $this->events($companyId, 100);
        $settings = $this->settings($companyId);
        $windowMinutes = $this->intSettingValue($settings, 'security.monitor.window_minutes', 5, 1, 1440);
        $maxretry = $this->intSettingValue($settings, 'security.fail2ban.maxretry', 10, 1, 100);
        $findtime = $this->intSettingValue($settings, 'security.fail2ban.findtime', 300, 60, 86400);
        $bantime = $this->intSettingValue($settings, 'security.fail2ban.bantime', 3600, 60, 604800);

        return [
            'fail2ban' => $this->fail2banStatus(),
            'firewall' => $this->firewallStatus(),
            'stats' => $this->eventStats($companyId, $windowMinutes),
            'attackers' => $this->topAttackers($companyId, $windowMinutes),
            'extensions' => $this->topExtensions($companyId, $windowMinutes),
            'events' => $events,
            'bans' => $this->bans($companyId, 50),
            'whitelist' => $this->whitelist($companyId),
            'blacklist' => $this->blacklist($companyId),
            'settings' => $settings,
            'policy' => [
                'window_minutes' => $windowMinutes,
                'maxretry' => $maxretry,
                'findtime' => $findtime,
                'bantime' => $bantime,
                'register_flood_threshold' => $this->intSettingValue($settings, 'security.monitor.register_flood_threshold', 10, 3, 1000),
                'multi_extension_threshold' => $this->intSettingValue($settings, 'security.monitor.multi_extension_threshold', 3, 2, 100),
                'event_retention_days' => $this->intSettingValue($settings, 'security.monitor.event_retention_days', 7, 1, 365),
                'findtime_human' => $this->humanSeconds($findtime),
                'bantime_human' => $this->humanSeconds($bantime),
            ],
        ];
    }

    public function fail2banStatus(): array
    {
        $global = $this->runCommand('fail2ban_status');
        $jail = $this->setting(null, 'security.fail2ban.jail_name', self::DEFAULT_JAIL);
        $jailStatus = $this->runCommand('fail2ban_status_jail', [$jail]);

        return [
            'available' => $global['ok'],
            'service' => $this->systemctlStatus('fail2ban'),
            'jail_name' => $jail,
            'jails' => $this->parseJails($global['output']),
            'banned_ips' => $this->parseBannedIps($jailStatus['output']),
            'summary' => $this->parseFail2banCounts($jailStatus['output']),
            'raw_status' => $global['output'],
            'raw_jail_status' => $jailStatus['output'],
            'recent_logs' => $this->recentFail2banLogs(),
        ];
    }

    public function firewallStatus(): array
    {
        $this->ensureSecurityChain();
        $rules = $this->runCommand('iptables_list_chain');

        return [
            'backend' => 'iptables',
            'chain' => 'UC200_SECURITY',
            'available' => $rules['ok'],
            'rules' => preg_split('/\r?\n/', trim($rules['output'])) ?: [],
        ];
    }

    public function events(?int $companyId, int $limit = 100): array
    {
        if ($companyId !== null && $companyId > 0) {
            $sql = 'SELECT *
                    FROM security_events
                    WHERE deleted_at IS NULL
                      AND (company_id = :company_id_exact OR company_id IS NULL)
                    ORDER BY detected_at DESC LIMIT :limit';
            $statement = $this->db->prepare($sql);
            $statement->bindValue(':company_id_exact', $companyId, PDO::PARAM_INT);
        } else {
            $sql = 'SELECT *
                    FROM security_events
                    WHERE deleted_at IS NULL
                    ORDER BY detected_at DESC LIMIT :limit';
            $statement = $this->db->prepare($sql);
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function (array $row): array {
            $row['details'] = $row['details_json'] !== null ? json_decode((string) $row['details_json'], true) : [];
            return $row;
        }, $rows ?: []);
    }

    public function bans(?int $companyId, int $limit = 100): array
    {
        if ($companyId !== null && $companyId > 0) {
            $sql = 'SELECT b.*, u.name AS created_by_name
                    FROM security_bans b
                    LEFT JOIN users u ON u.id = b.created_by
                    WHERE (b.company_id = :company_id_exact OR b.company_id IS NULL)
                    ORDER BY b.created_at DESC LIMIT :limit';
            $statement = $this->db->prepare($sql);
            $statement->bindValue(':company_id_exact', $companyId, PDO::PARAM_INT);
        } else {
            $sql = 'SELECT b.*, u.name AS created_by_name
                    FROM security_bans b
                    LEFT JOIN users u ON u.id = b.created_by
                    ORDER BY b.created_at DESC LIMIT :limit';
            $statement = $this->db->prepare($sql);
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function whitelist(?int $companyId): array
    {
        return $this->ipList('security_ip_whitelist', $companyId);
    }

    public function blacklist(?int $companyId): array
    {
        return $this->ipList('security_ip_blacklist', $companyId);
    }

    public function settings(?int $companyId): array
    {
        $defaults = [
            'security.fail2ban.jail_name' => self::DEFAULT_JAIL,
            'security.fail2ban.maxretry' => '10',
            'security.fail2ban.findtime' => '300',
            'security.fail2ban.bantime' => '3600',
            'security.fail2ban.enabled' => '1',
            'security.fail2ban.logpath' => '/var/log/asterisk/messages',
            'security.monitor.window_minutes' => '5',
            'security.monitor.register_flood_threshold' => '10',
            'security.monitor.multi_extension_threshold' => '3',
            'security.monitor.event_retention_days' => '7',
        ];

        if ($companyId !== null && $companyId > 0) {
            $sql = 'SELECT setting_key, setting_value, is_enabled
                    FROM security_settings
                    WHERE deleted_at IS NULL AND (company_id = :company_id_exact OR company_id IS NULL)
                    ORDER BY company_id IS NULL ASC';
            $statement = $this->db->prepare($sql);
            $statement->execute(['company_id_exact' => $companyId]);
        } else {
            $sql = 'SELECT setting_key, setting_value, is_enabled
                    FROM security_settings
                    WHERE deleted_at IS NULL AND company_id IS NULL';
            $statement = $this->db->prepare($sql);
            $statement->execute();
        }
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $defaults[(string) $row['setting_key']] = (string) $row['setting_value'];
            $defaults[(string) $row['setting_key'] . '.enabled'] = (bool) $row['is_enabled'];
        }

        return $defaults;
    }

    public function saveSettings(?int $companyId, int $userId, array $input): void
    {
        $allowed = [
            'security.fail2ban.jail_name' => preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($input['jail_name'] ?? self::DEFAULT_JAIL)),
            'security.fail2ban.maxretry' => (string) max(1, min(100, (int) ($input['maxretry'] ?? 10))),
            'security.fail2ban.findtime' => (string) max(60, min(86400, (int) ($input['findtime'] ?? 300))),
            'security.fail2ban.bantime' => (string) max(60, min(604800, (int) ($input['bantime'] ?? 3600))),
            'security.fail2ban.logpath' => $this->sanitizeLogpath((string) ($input['logpath'] ?? '/var/log/asterisk/messages')),
            'security.fail2ban.enabled' => ! empty($input['enabled']) ? '1' : '0',
            'security.monitor.window_minutes' => (string) max(1, min(1440, (int) ($input['window_minutes'] ?? 5))),
            'security.monitor.register_flood_threshold' => (string) max(3, min(1000, (int) ($input['register_flood_threshold'] ?? 10))),
            'security.monitor.multi_extension_threshold' => (string) max(2, min(100, (int) ($input['multi_extension_threshold'] ?? 3))),
            'security.monitor.event_retention_days' => (string) max(1, min(365, (int) ($input['event_retention_days'] ?? 7))),
        ];

        foreach ($allowed as $key => $value) {
            $existingId = $this->settingRecordId($companyId, $key);
            if ($existingId !== null) {
                $statement = $this->db->prepare(
                    'UPDATE security_settings
                     SET setting_value = :setting_value, is_enabled = 1, created_by = :created_by, deleted_at = NULL
                     WHERE id = :id'
                );
                $statement->execute([
                    'setting_value' => $value,
                    'created_by' => $userId,
                    'id' => $existingId,
                ]);
                continue;
            }

            $statement = $this->db->prepare(
                'INSERT INTO security_settings (uuid, company_id, setting_key, setting_value, is_enabled, created_by)
                 VALUES (:uuid, :company_id, :setting_key, :setting_value, 1, :created_by)'
            );
            $statement->execute([
                'uuid' => uuid(),
                'company_id' => $companyId,
                'setting_key' => $key,
                'setting_value' => $value,
                'created_by' => $userId,
            ]);
        }
    }

    public function addWhitelist(?int $companyId, int $userId, string $ip, string $reason, string $source = 'panel'): array
    {
        $ip = $this->validatedIp($ip);
        $this->upsertIpList('security_ip_whitelist', $companyId, $userId, $ip, $reason, $source);
        $this->recordBanAction($companyId, $userId, $ip, 'whitelist_add', $reason, $source, 'Whitelist entry added.');

        return ['ok' => true, 'message' => 'IP agregada a whitelist.'];
    }

    public function removeWhitelist(?int $companyId, int $userId, string $uuid): array
    {
        $row = $this->ipListEntry('security_ip_whitelist', $companyId, $uuid);
        $this->softDeleteIpList('security_ip_whitelist', (int) $row['id']);
        $this->recordBanAction($companyId, $userId, (string) $row['ip'], 'whitelist_remove', (string) ($row['reason'] ?? ''), 'panel', 'Whitelist entry removed.');

        return ['ok' => true, 'message' => 'IP eliminada de whitelist.'];
    }

    public function addBlacklist(?int $companyId, int $userId, string $ip, string $reason, string $source = 'panel'): array
    {
        $ip = $this->validatedIp($ip);
        $this->upsertIpList('security_ip_blacklist', $companyId, $userId, $ip, $reason, $source);
        $firewall = $this->blockFirewallIp($companyId, $userId, $ip, 'Blacklist manual');
        $this->recordBanAction($companyId, $userId, $ip, 'blacklist_add', $reason, $source, $firewall['message']);

        return ['ok' => true, 'message' => 'IP agregada a blacklist y bloqueada en firewall.'];
    }

    public function removeBlacklist(?int $companyId, int $userId, string $uuid): array
    {
        $row = $this->ipListEntry('security_ip_blacklist', $companyId, $uuid);
        $this->softDeleteIpList('security_ip_blacklist', (int) $row['id']);
        $firewall = $this->unblockFirewallIp($companyId, $userId, (string) $row['ip'], 'Blacklist removal');
        $this->recordBanAction($companyId, $userId, (string) $row['ip'], 'blacklist_remove', (string) ($row['reason'] ?? ''), 'panel', $firewall['message']);

        return ['ok' => true, 'message' => 'IP eliminada de blacklist.'];
    }

    public function banIp(?int $companyId, int $userId, string $ip, string $reason, string $source = 'panel'): array
    {
        $ip = $this->validatedIp($ip);
        if ($this->isWhitelisted($companyId, $ip)) {
            return ['ok' => false, 'message' => 'La IP esta en whitelist y no se puede banear.'];
        }

        $jail = $this->setting($companyId, 'security.fail2ban.jail_name', self::DEFAULT_JAIL);
        $result = $this->runCommand('fail2ban_banip', [$jail, $ip]);
        $this->recordBanAction($companyId, $userId, $ip, 'ban', $reason, $source, $result['output']);

        return [
            'ok' => $result['ok'],
            'message' => $result['ok'] ? 'IP baneada correctamente.' : 'No se pudo banear la IP.',
            'output' => $result['output'],
        ];
    }

    public function unbanIp(?int $companyId, int $userId, string $ip, string $reason = '', string $source = 'panel'): array
    {
        $ip = $this->validatedIp($ip);
        $jail = $this->setting($companyId, 'security.fail2ban.jail_name', self::DEFAULT_JAIL);
        $result = $this->runCommand('fail2ban_unbanip', [$jail, $ip]);
        $this->recordBanAction($companyId, $userId, $ip, 'unban', $reason, $source, $result['output']);

        return [
            'ok' => $result['ok'],
            'message' => $result['ok'] ? 'IP desbaneada correctamente.' : 'No se pudo desbanear la IP.',
            'output' => $result['output'],
        ];
    }

    public function restartFail2ban(?int $companyId, int $userId): array
    {
        $result = $this->runCommand('systemctl_restart', ['fail2ban']);
        $this->recordBanAction($companyId, $userId, '127.0.0.1', 'fail2ban_restart', 'Manual restart', 'panel', $result['output']);

        return [
            'ok' => $result['ok'],
            'message' => $result['ok'] ? 'Fail2Ban reiniciado.' : 'No se pudo reiniciar Fail2Ban.',
            'output' => $result['output'],
        ];
    }

    public function reloadFail2ban(?int $companyId, int $userId): array
    {
        $result = $this->runCommand('fail2ban_reload');
        $this->recordBanAction($companyId, $userId, '127.0.0.1', 'fail2ban_reload', 'Manual reload', 'panel', $result['output']);

        return [
            'ok' => $result['ok'],
            'message' => $result['ok'] ? 'Fail2Ban recargado.' : 'No se pudo recargar Fail2Ban.',
            'output' => $result['output'],
        ];
    }

    public function blockFirewallIp(?int $companyId, int $userId, string $ip, string $reason, string $source = 'panel'): array
    {
        $ip = $this->validatedIp($ip);
        $this->ensureSecurityChain();
        $result = $this->runCommand('iptables_add_drop', [$ip]);
        $this->recordBanAction($companyId, $userId, $ip, 'firewall_block', $reason, $source, $result['output']);

        return [
            'ok' => $result['ok'],
            'message' => $result['ok'] ? 'IP bloqueada en firewall.' : 'No se pudo bloquear la IP en firewall.',
            'output' => $result['output'],
        ];
    }

    public function unblockFirewallIp(?int $companyId, int $userId, string $ip, string $reason, string $source = 'panel'): array
    {
        $ip = $this->validatedIp($ip);
        $this->ensureSecurityChain();
        $result = $this->runCommand('iptables_delete_drop', [$ip]);
        $this->recordBanAction($companyId, $userId, $ip, 'firewall_unblock', $reason, $source, $result['output']);

        return [
            'ok' => $result['ok'],
            'message' => $result['ok'] ? 'IP desbloqueada en firewall.' : 'No se pudo desbloquear la IP en firewall.',
            'output' => $result['output'],
        ];
    }

    public function persistFirewallRules(?int $companyId, int $userId): array
    {
        $result = $this->runCommand('iptables_persist');
        $this->recordBanAction($companyId, $userId, '127.0.0.1', 'firewall_persist', 'Persist firewall rules', 'panel', $result['output']);

        return [
            'ok' => $result['ok'],
            'message' => $result['ok'] ? 'Reglas UC200 persistidas.' : 'No se pudieron persistir las reglas.',
            'output' => $result['output'],
        ];
    }

    public function syncSecurityEvents(?int $companyId): void
    {
        $settings = $this->settings($companyId);
        $retentionDays = $this->intSettingValue($settings, 'security.monitor.event_retention_days', 7, 1, 365);
        $this->db->exec('DELETE FROM security_events WHERE detected_at < (NOW() - INTERVAL ' . $retentionDays . ' DAY)');

        foreach ($this->parseAttackEvents() as $event) {
            $hash = hash('sha256', json_encode($event));
            $statement = $this->db->prepare(
                'INSERT IGNORE INTO security_events
                 (uuid, company_id, ip, event_type, severity, extension_hint, event_hash, source, country_code, country_name, details_json, detected_at)
                 VALUES
                 (:uuid, :company_id, :ip, :event_type, :severity, :extension_hint, :event_hash, :source, :country_code, :country_name, :details_json, :detected_at)'
            );
            $statement->execute([
                'uuid' => uuid(),
                'company_id' => $companyId,
                'ip' => $event['ip'],
                'event_type' => $event['event_type'],
                'severity' => $event['severity'],
                'extension_hint' => $event['extension_hint'],
                'event_hash' => $hash,
                'source' => $event['source'],
                'country_code' => $event['country_code'],
                'country_name' => $event['country_name'],
                'details_json' => json_encode($event['details'], JSON_UNESCAPED_SLASHES),
                'detected_at' => $event['detected_at'],
            ]);
        }
    }

    private function eventStats(?int $companyId, int $windowMinutes): array
    {
        $windowMinutes = max(1, min(1440, $windowMinutes));
        $base = 'FROM security_events WHERE deleted_at IS NULL AND detected_at >= (NOW() - INTERVAL ' . $windowMinutes . ' MINUTE)';
        if ($companyId !== null && $companyId > 0) {
            $base .= ' AND (company_id = :company_id_exact OR company_id IS NULL)';
            $params = ['company_id_exact' => $companyId];
        } else {
            $params = [];
        }

        return [
            'failed_last_5m' => $this->countFrom($base, $params),
            'critical_last_5m' => $this->countFrom($base . ' AND severity = "critical"', $params),
            'attackers_last_5m' => $this->countDistinctFrom('ip', $base, $params),
            'extensions_last_5m' => $this->countDistinctFrom('extension_hint', $base, $params),
            'active_bans' => count($this->fail2banStatus()['banned_ips']),
        ];
    }

    private function topAttackers(?int $companyId, int $windowMinutes): array
    {
        return $this->aggregateEvents($companyId, 'ip', '1 = 1', $windowMinutes, true);
    }

    private function topExtensions(?int $companyId, int $windowMinutes): array
    {
        return $this->aggregateEvents($companyId, 'extension_hint', 'extension_hint IS NOT NULL AND extension_hint <> ""', $windowMinutes);
    }

    private function aggregateEvents(?int $companyId, string $column, string $extraWhere = '1 = 1', int $windowMinutes = 5, bool $includeCritical = false): array
    {
        $windowMinutes = max(1, min(1440, $windowMinutes));
        $sql = 'SELECT ' . $column . ' AS label, COUNT(*) AS total
                ' . ($includeCritical ? ', SUM(CASE WHEN severity = "critical" THEN 1 ELSE 0 END) AS critical_total, COUNT(DISTINCT extension_hint) AS extensions_targeted, MAX(detected_at) AS last_seen' : '') . '
                FROM security_events
                WHERE deleted_at IS NULL
                  AND detected_at >= (NOW() - INTERVAL ' . $windowMinutes . ' MINUTE)
                  AND ' . $extraWhere;
        if ($companyId !== null && $companyId > 0) {
            $sql .= ' AND (company_id = :company_id_exact OR company_id IS NULL)';
            $params = ['company_id_exact' => $companyId];
        } else {
            $params = [];
        }

        $sql .= ' GROUP BY ' . $column . ' ORDER BY total DESC LIMIT 10';
        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function parseAttackEvents(): array
    {
        $events = [];
        $attemptsByIp = [];
        $extensionsByIp = [];
        $settings = $this->settings(null);
        $registerFloodThreshold = $this->intSettingValue($settings, 'security.monitor.register_flood_threshold', 10, 3, 1000);
        $multiExtensionThreshold = $this->intSettingValue($settings, 'security.monitor.multi_extension_threshold', 3, 2, 100);

        foreach ($this->recentAsteriskLines() as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (preg_match('/\[(?<date>[A-Z][a-z]{2} \d{1,2} \d{2}:\d{2}:\d{2})\].*Request \'.*\' from \'"(?<extension>[^"]*)".*failed for \'(?<ip>[^:\']+)/', $line, $match)) {
                $event = $this->buildEvent($match['ip'], 'failed_auth', 'critical', $match['extension'], $line, $match['date']);
                $events[] = $event;
                $attemptsByIp[$event['ip']] = ($attemptsByIp[$event['ip']] ?? 0) + 1;
                if ($event['extension_hint'] !== null) {
                    $extensionsByIp[$event['ip']][$event['extension_hint']] = true;
                }
                continue;
            }

            if (preg_match('/No matching endpoint found.*for \'(?<ip>[^:\']+)/i', $line, $match)) {
                $events[] = $this->buildEvent($match['ip'], 'no_matching_endpoint', 'warning', null, $line, null);
                $attemptsByIp[$match['ip']] = ($attemptsByIp[$match['ip']] ?? 0) + 1;
                continue;
            }

            if (preg_match('/Invalid password.*from .*?(?<ip>\d+\.\d+\.\d+\.\d+)/i', $line, $match)) {
                $events[] = $this->buildEvent($match['ip'], 'invalid_password', 'critical', null, $line, null);
                $attemptsByIp[$match['ip']] = ($attemptsByIp[$match['ip']] ?? 0) + 1;
                continue;
            }

            if (preg_match('/friendly-scanner|sipvicious|vaxsipuseragent|sipsak|scanner/i', $line) && preg_match('/(?<ip>\d+\.\d+\.\d+\.\d+)/', $line, $match)) {
                $events[] = $this->buildEvent($match['ip'], 'sip_scanner', 'critical', null, $line, null);
                $attemptsByIp[$match['ip']] = ($attemptsByIp[$match['ip']] ?? 0) + 1;
            }
        }

        foreach ($attemptsByIp as $ip => $count) {
            if ($count >= $registerFloodThreshold) {
                $events[] = $this->buildEvent($ip, 'register_flood', 'critical', null, 'Register flood detected from the same IP.', null, [
                    'attempts' => $count,
                    'threshold' => $registerFloodThreshold,
                ]);
            }

            $extensionCount = isset($extensionsByIp[$ip]) ? count($extensionsByIp[$ip]) : 0;
            if ($extensionCount >= $multiExtensionThreshold) {
                $events[] = $this->buildEvent($ip, 'multi_extension_probe', 'critical', null, 'Multiple extensions attempted from the same IP.', null, [
                    'extensions' => array_keys($extensionsByIp[$ip]),
                    'threshold' => $multiExtensionThreshold,
                ]);
            }
        }

        return $events;
    }

    private function buildEvent(string $ip, string $type, string $severity, ?string $extension, string $message, ?string $date, array $details = []): array
    {
        $geo = $this->geoIp($ip);

        return [
            'ip' => $ip,
            'event_type' => $type,
            'severity' => $severity,
            'extension_hint' => $extension !== null && $extension !== '' ? $extension : null,
            'source' => 'asterisk',
            'country_code' => $geo['country_code'],
            'country_name' => $geo['country_name'],
            'details' => ['message' => $message] + $details,
            'detected_at' => $this->normalizeDetectedAt($date),
        ];
    }

    private function recentAsteriskLines(): array
    {
        $paths = array_values(array_unique(array_filter([
            $this->setting(null, 'security.fail2ban.logpath', '/var/log/asterisk/messages'),
            '/var/log/asterisk/messages',
            '/var/log/asterisk/full',
        ])));

        foreach ($paths as $path) {
            if (is_file($path) && is_readable($path)) {
                $result = $this->runCommand('tail_file', [$path, '250']);
                if ($result['ok']) {
                    return preg_split('/\r?\n/', trim($result['output'])) ?: [];
                }
            }
        }

        $result = $this->runCommand('journalctl_asterisk');
        return $result['ok'] ? (preg_split('/\r?\n/', trim($result['output'])) ?: []) : [];
    }

    private function recentFail2banLogs(): array
    {
        $result = $this->runCommand('journalctl_fail2ban');
        return $result['ok'] ? (preg_split('/\r?\n/', trim($result['output'])) ?: []) : [];
    }

    private function ensureSecurityChain(): void
    {
        $this->runCommand('iptables_create_chain');
        $this->runCommand('iptables_hook_chain');
    }

    private function runCommand(string $key, array $arguments = []): array
    {
        $command = $this->command($key, $arguments);
        $descriptor = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($command, $descriptor, $pipes);

        if (! is_resource($process)) {
            return ['ok' => false, 'output' => 'Unable to start command.'];
        }

        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);

        return [
            'ok' => $status === 0,
            'output' => trim($stdout . ($stderr !== '' ? PHP_EOL . $stderr : '')),
        ];
    }

    private function command(string $key, array $arguments): string
    {
        return match ($key) {
            'fail2ban_status' => $this->privileged($this->binary('fail2ban-client') . ' status'),
            'fail2ban_status_jail' => $this->privileged($this->binary('fail2ban-client') . ' status ' . escapeshellarg($arguments[0] ?? self::DEFAULT_JAIL)),
            'fail2ban_banip' => $this->privileged($this->binary('fail2ban-client') . ' set ' . escapeshellarg($arguments[0] ?? self::DEFAULT_JAIL) . ' banip ' . escapeshellarg($arguments[1] ?? '')),
            'fail2ban_unbanip' => $this->privileged($this->binary('fail2ban-client') . ' set ' . escapeshellarg($arguments[0] ?? self::DEFAULT_JAIL) . ' unbanip ' . escapeshellarg($arguments[1] ?? '')),
            'fail2ban_reload' => $this->privileged($this->binary('fail2ban-client') . ' reload'),
            'systemctl_restart' => $this->privileged($this->binary('systemctl') . ' restart ' . escapeshellarg($arguments[0] ?? '')),
            'systemctl_status' => $this->binary('systemctl') . ' is-active ' . escapeshellarg($arguments[0] ?? ''),
            'tail_file' => $this->binary('tail') . ' -n ' . (int) ($arguments[1] ?? 100) . ' ' . escapeshellarg($arguments[0] ?? ''),
            'journalctl_asterisk' => $this->binary('journalctl') . ' -u asterisk -n 250 --no-pager',
            'journalctl_fail2ban' => $this->binary('journalctl') . ' -u fail2ban -n 120 --no-pager',
            'iptables_create_chain' => $this->privileged($this->binary('sh') . ' -c ' . escapeshellarg($this->binary('iptables') . ' -N UC200_SECURITY 2>/dev/null || true')),
            'iptables_hook_chain' => $this->privileged($this->binary('sh') . ' -c ' . escapeshellarg($this->binary('iptables') . ' -C INPUT -j UC200_SECURITY 2>/dev/null || ' . $this->binary('iptables') . ' -I INPUT -j UC200_SECURITY')),
            'iptables_add_drop' => $this->privileged($this->binary('sh') . ' -c ' . escapeshellarg($this->binary('iptables') . ' -C UC200_SECURITY -s ' . escapeshellarg($arguments[0] ?? '') . ' -j DROP 2>/dev/null || ' . $this->binary('iptables') . ' -A UC200_SECURITY -s ' . escapeshellarg($arguments[0] ?? '') . ' -j DROP')),
            'iptables_delete_drop' => $this->privileged($this->binary('sh') . ' -c ' . escapeshellarg($this->binary('iptables') . ' -D UC200_SECURITY -s ' . escapeshellarg($arguments[0] ?? '') . ' -j DROP 2>/dev/null || true')),
            'iptables_list_chain' => $this->privileged($this->binary('iptables') . ' -S UC200_SECURITY'),
            'iptables_persist' => $this->privileged($this->binary('sh') . ' -c ' . escapeshellarg($this->persistCommand())),
            default => throw new RuntimeException('Unsupported command key: ' . $key),
        };
    }

    private function systemctlStatus(string $service): string
    {
        $result = $this->runCommand('systemctl_status', [$service]);
        return $result['ok'] ? trim($result['output']) : 'unknown';
    }

    private function persistCommand(): string
    {
        $iptablesSave = $this->binary('iptables-save');
        if (is_dir('/etc/iptables')) {
            return $iptablesSave . ' > /etc/iptables/rules.v4';
        }
        if (is_dir('/etc/sysconfig')) {
            return $iptablesSave . ' > /etc/sysconfig/iptables';
        }

        return $iptablesSave;
    }

    private function binary(string $name): string
    {
        $candidates = [
            'fail2ban-client' => ['/usr/bin/fail2ban-client', '/usr/local/bin/fail2ban-client', 'fail2ban-client'],
            'systemctl' => ['/usr/bin/systemctl', '/bin/systemctl', 'systemctl'],
            'journalctl' => ['/usr/bin/journalctl', '/bin/journalctl', 'journalctl'],
            'tail' => ['/usr/bin/tail', '/bin/tail', 'tail'],
            'iptables' => ['/usr/sbin/iptables', '/sbin/iptables', 'iptables'],
            'iptables-save' => ['/usr/sbin/iptables-save', '/sbin/iptables-save', 'iptables-save'],
            'sh' => ['/bin/sh', 'sh'],
            'sudo' => ['/usr/bin/sudo', '/bin/sudo', 'sudo'],
        ][$name] ?? [$name];

        foreach ($candidates as $candidate) {
            if ($candidate === $name || is_executable($candidate)) {
                return $candidate;
            }
        }

        return $name;
    }

    private function privileged(string $command): string
    {
        if (! $this->useSudo()) {
            return $command;
        }

        return $this->binary('sudo') . ' -n ' . $command;
    }

    private function useSudo(): bool
    {
        return env('SECURITY_USE_SUDO', true) !== false;
    }

    private function parseJails(string $output): array
    {
        if (! preg_match('/Jail list:\s*(.+)/i', $output, $match)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $match[1]))));
    }

    private function parseBannedIps(string $output): array
    {
        if (! preg_match('/Banned IP list:\s*(.+)/i', $output, $match)) {
            return [];
        }

        return array_values(array_filter(preg_split('/\s+/', trim((string) $match[1])) ?: []));
    }

    private function parseFail2banCounts(string $output): array
    {
        $currentlyFailed = preg_match('/Currently failed:\s*(\d+)/i', $output, $failed) ? (int) $failed[1] : 0;
        $currentlyBanned = preg_match('/Currently banned:\s*(\d+)/i', $output, $banned) ? (int) $banned[1] : 0;
        $totalBanned = preg_match('/Total banned:\s*(\d+)/i', $output, $total) ? (int) $total[1] : 0;

        return [
            'currently_failed' => $currentlyFailed,
            'currently_banned' => $currentlyBanned,
            'total_banned' => $totalBanned,
        ];
    }

    private function validatedIp(string $ip): string
    {
        $ip = trim($ip);
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new RuntimeException('IP address is not valid.');
        }

        return $ip;
    }

    private function ipList(string $table, ?int $companyId): array
    {
        if ($companyId !== null && $companyId > 0) {
            $sql = 'SELECT l.*, u.name AS created_by_name
                    FROM ' . $table . ' l
                    LEFT JOIN users u ON u.id = l.created_by
                    WHERE l.deleted_at IS NULL AND l.status = "active"
                      AND (l.company_id = :company_id_exact OR l.company_id IS NULL)
                    ORDER BY l.created_at DESC';
            $statement = $this->db->prepare($sql);
            $statement->execute(['company_id_exact' => $companyId]);
        } else {
            $sql = 'SELECT l.*, u.name AS created_by_name
                    FROM ' . $table . ' l
                    LEFT JOIN users u ON u.id = l.created_by
                    WHERE l.deleted_at IS NULL AND l.status = "active"
                    ORDER BY l.created_at DESC';
            $statement = $this->db->prepare($sql);
            $statement->execute();
        }
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function upsertIpList(string $table, ?int $companyId, int $userId, string $ip, string $reason, string $source): void
    {
        $existingId = $this->ipListRecordId($table, $companyId, $ip);
        if ($existingId !== null) {
            $statement = $this->db->prepare(
                'UPDATE ' . $table . '
                 SET reason = :reason, source = :source, created_by = :created_by, status = "active", deleted_at = NULL
                 WHERE id = :id'
            );
            $statement->execute([
                'reason' => $reason,
                'source' => $source,
                'created_by' => $userId,
                'id' => $existingId,
            ]);
            return;
        }

        $statement = $this->db->prepare(
            'INSERT INTO ' . $table . ' (uuid, company_id, ip, reason, source, created_by, expires_at, status)
             VALUES (:uuid, :company_id, :ip, :reason, :source, :created_by, NULL, "active")'
        );
        $statement->execute([
            'uuid' => uuid(),
            'company_id' => $companyId,
            'ip' => $ip,
            'reason' => $reason,
            'source' => $source,
            'created_by' => $userId,
        ]);
    }

    private function ipListEntry(string $table, ?int $companyId, string $uuid): array
    {
        $sql = 'SELECT * FROM ' . $table . ' WHERE uuid = :uuid AND deleted_at IS NULL LIMIT 1';
        $statement = $this->db->prepare($sql);
        $statement->execute(['uuid' => $uuid]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (! is_array($row)) {
            throw new RuntimeException('Security list entry not found.');
        }
        if ($companyId !== null && $companyId > 0 && $row['company_id'] !== null && (int) $row['company_id'] !== $companyId) {
            throw new RuntimeException('Security list entry is outside the current tenant.');
        }

        return $row;
    }

    private function softDeleteIpList(string $table, int $id): void
    {
        $statement = $this->db->prepare('UPDATE ' . $table . ' SET status = "inactive", deleted_at = NOW() WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    private function recordBanAction(?int $companyId, int $userId, string $ip, string $action, string $reason, string $source, string $output): void
    {
        $statement = $this->db->prepare(
            'INSERT INTO security_bans
             (uuid, company_id, ip, jail_name, action, reason, source, command_output, created_by, expires_at)
             VALUES (:uuid, :company_id, :ip, :jail_name, :action, :reason, :source, :command_output, :created_by, NULL)'
        );
        $statement->execute([
            'uuid' => uuid(),
            'company_id' => $companyId,
            'ip' => $ip,
            'jail_name' => $this->setting($companyId, 'security.fail2ban.jail_name', self::DEFAULT_JAIL),
            'action' => $action,
            'reason' => $reason,
            'source' => $source,
            'command_output' => $output,
            'created_by' => $userId,
        ]);
    }

    private function setting(?int $companyId, string $key, string $fallback): string
    {
        if ($companyId !== null && $companyId > 0) {
            $statement = $this->db->prepare(
                'SELECT setting_value
                 FROM security_settings
                 WHERE setting_key = :setting_key
                   AND deleted_at IS NULL
                   AND (company_id = :company_id_exact OR company_id IS NULL)
                 ORDER BY company_id IS NULL ASC
                 LIMIT 1'
            );
            $statement->execute([
                'setting_key' => $key,
                'company_id_exact' => $companyId,
            ]);
        } else {
            $statement = $this->db->prepare(
                'SELECT setting_value
                 FROM security_settings
                 WHERE setting_key = :setting_key
                   AND deleted_at IS NULL
                   AND company_id IS NULL
                 LIMIT 1'
            );
            $statement->execute([
                'setting_key' => $key,
            ]);
        }
        $value = $statement->fetchColumn();

        return $value !== false ? (string) $value : $fallback;
    }

    private function sanitizeLogpath(string $path): string
    {
        $path = trim($path);
        if (! str_starts_with($path, '/')) {
            return '/var/log/asterisk/messages';
        }

        return preg_replace('/[^a-zA-Z0-9_\/\.\-]/', '', $path) ?: '/var/log/asterisk/messages';
    }

    private function geoIp(string $ip): array
    {
        if (function_exists('geoip_country_code_by_name')) {
            return [
                'country_code' => geoip_country_code_by_name($ip) ?: null,
                'country_name' => geoip_country_name_by_name($ip) ?: null,
            ];
        }

        return ['country_code' => null, 'country_name' => null];
    }

    private function normalizeDetectedAt(?string $date): string
    {
        if ($date === null || $date === '') {
            return date('Y-m-d H:i:s');
        }

        $timestamp = strtotime($date . ' ' . date('Y'));
        if ($timestamp === false) {
            return date('Y-m-d H:i:s');
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function humanSeconds(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . 's';
        }
        if ($seconds < 3600) {
            return (string) round($seconds / 60, 1) . 'm';
        }
        if ($seconds < 86400) {
            return (string) round($seconds / 3600, 1) . 'h';
        }

        return (string) round($seconds / 86400, 1) . 'd';
    }

    private function intSettingValue(array $settings, string $key, int $fallback, int $min, int $max): int
    {
        $value = isset($settings[$key]) ? (int) $settings[$key] : $fallback;
        return max($min, min($max, $value));
    }

    private function countFrom(string $fromSql, array $params): int
    {
        $statement = $this->db->prepare('SELECT COUNT(*) ' . $fromSql);
        $statement->execute($params);
        return (int) $statement->fetchColumn();
    }

    private function countDistinctFrom(string $column, string $fromSql, array $params): int
    {
        $statement = $this->db->prepare('SELECT COUNT(DISTINCT ' . $column . ') ' . $fromSql);
        $statement->execute($params);
        return (int) $statement->fetchColumn();
    }

    private function isWhitelisted(?int $companyId, string $ip): bool
    {
        if ($companyId !== null && $companyId > 0) {
            $statement = $this->db->prepare(
                'SELECT COUNT(*)
                 FROM security_ip_whitelist
                 WHERE ip = :ip
                   AND status = "active"
                   AND deleted_at IS NULL
                   AND (company_id = :company_id_exact OR company_id IS NULL)'
            );
            $statement->execute([
                'ip' => $ip,
                'company_id_exact' => $companyId,
            ]);
        } else {
            $statement = $this->db->prepare(
                'SELECT COUNT(*)
                 FROM security_ip_whitelist
                 WHERE ip = :ip
                   AND status = "active"
                   AND deleted_at IS NULL'
            );
            $statement->execute([
                'ip' => $ip,
            ]);
        }

        return (int) $statement->fetchColumn() > 0;
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->db->prepare(
            'SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = :table'
        );
        $statement->execute(['table' => $table]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function settingRecordId(?int $companyId, string $key): ?int
    {
        if ($companyId !== null && $companyId > 0) {
            $statement = $this->db->prepare(
                'SELECT id
                 FROM security_settings
                 WHERE setting_key = :setting_key
                   AND deleted_at IS NULL
                   AND company_id = :company_id_exact
                 LIMIT 1'
            );
            $statement->execute([
                'setting_key' => $key,
                'company_id_exact' => $companyId,
            ]);
        } else {
            $statement = $this->db->prepare(
                'SELECT id
                 FROM security_settings
                 WHERE setting_key = :setting_key
                   AND deleted_at IS NULL
                   AND company_id IS NULL
                 LIMIT 1'
            );
            $statement->execute([
                'setting_key' => $key,
            ]);
        }
        $id = $statement->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    private function ipListRecordId(string $table, ?int $companyId, string $ip): ?int
    {
        if ($companyId !== null && $companyId > 0) {
            $statement = $this->db->prepare(
                'SELECT id
                 FROM ' . $table . '
                 WHERE ip = :ip
                   AND deleted_at IS NULL
                   AND company_id = :company_id_exact
                 LIMIT 1'
            );
            $statement->execute([
                'ip' => $ip,
                'company_id_exact' => $companyId,
            ]);
        } else {
            $statement = $this->db->prepare(
                'SELECT id
                 FROM ' . $table . '
                 WHERE ip = :ip
                   AND deleted_at IS NULL
                   AND company_id IS NULL
                 LIMIT 1'
            );
            $statement->execute([
                'ip' => $ip,
            ]);
        }
        $id = $statement->fetchColumn();

        return $id !== false ? (int) $id : null;
    }
}
