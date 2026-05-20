<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class SoftphoneService
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function settings(?int $companyId, ?string $hostOverride = null): array
    {
        $statement = $this->db->prepare(
            'SELECT * FROM webphone_settings
             WHERE (company_id = :company_id OR company_id IS NULL) AND deleted_at IS NULL
             ORDER BY company_id DESC
             LIMIT 1'
        );
        $statement->execute(['company_id' => $companyId]);
        $row = $statement->fetch() ?: [];

        $host = $hostOverride ?: ($_SERVER['HTTP_HOST'] ?? env('APP_URL', 'localhost'));
        $host = preg_replace('/:\d+$/', '', (string) $host);
        $sipDomain = trim((string) ($row['sip_domain'] ?? '')) ?: (string) $host;
        $port = max(1, (int) ($row['websocket_port'] ?? 8089));
        $path = trim((string) ($row['websocket_path'] ?? '/ws')) ?: '/ws';
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        $wssUrl = trim((string) ($row['wss_url'] ?? ''));
        if ($wssUrl === '') {
            $wssUrl = $path;
        }

        $stunServer = trim((string) ($row['stun_server'] ?? ''));
        $turnServer = trim((string) ($row['turn_server'] ?? ''));
        $stunUrls = $this->normalizeList($row['stun_urls'] ?? null, $stunServer !== '' ? [$stunServer] : ['stun:stun.l.google.com:19302']);
        $turnUrls = $this->normalizeList($row['turn_urls'] ?? null, $turnServer !== '' ? [$turnServer] : []);

        return [
            'company_id' => $row['company_id'] ?? $companyId,
            'sip_domain' => $sipDomain,
            'wss_url' => $wssUrl,
            'wss_mode' => str_starts_with($wssUrl, 'ws') ? 'absolute' : 'relative',
            'enable_webrtc' => (string) ($row['enable_webrtc'] ?? 'yes'),
            'websocket_port' => $port,
            'websocket_path' => $path,
            'stun_server' => $stunServer !== '' ? $stunServer : ($stunUrls[0] ?? ''),
            'turn_server' => $turnServer !== '' ? $turnServer : ($turnUrls[0] ?? ''),
            'stun_urls' => $stunUrls,
            'turn_urls' => $turnUrls,
            'dtls_enabled' => (string) ($row['dtls_enabled'] ?? 'yes'),
            'ice_enabled' => (string) ($row['ice_enabled'] ?? 'yes'),
            'notifications_enabled' => (string) ($row['notifications_enabled'] ?? 'yes'),
            'session_timeout_minutes' => max(5, (int) ($row['session_timeout_minutes'] ?? 480)),
            'status' => (string) ($row['status'] ?? 'active'),
        ];
    }

    public function saveSettings(?int $companyId, array $input, ?string $hostOverride = null): array
    {
        $host = $hostOverride ?: ($_SERVER['HTTP_HOST'] ?? env('APP_URL', 'localhost'));
        $host = preg_replace('/:\d+$/', '', (string) $host);
        $sipDomain = trim((string) ($input['sip_domain'] ?? '')) ?: (string) $host;
        $port = max(1, min(65535, (int) ($input['websocket_port'] ?? 8089)));
        $path = trim((string) ($input['websocket_path'] ?? '/ws')) ?: '/ws';
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        $stunServer = trim((string) ($input['stun_server'] ?? ''));
        $turnServer = trim((string) ($input['turn_server'] ?? ''));
        $enableWebRtc = $this->yesNo($input['enable_webrtc'] ?? 'yes');
        $dtlsEnabled = $this->yesNo($input['dtls_enabled'] ?? 'yes');
        $iceEnabled = $this->yesNo($input['ice_enabled'] ?? 'yes');
        $notifications = $this->yesNo($input['notifications_enabled'] ?? 'yes');
        $sessionTimeout = max(5, min(1440, (int) ($input['session_timeout_minutes'] ?? 480)));
        $providedWssUrl = trim((string) ($input['wss_url'] ?? ''));
        $wssUrl = $providedWssUrl !== '' ? $providedWssUrl : $path;
        $stunUrls = $stunServer !== '' ? [$stunServer] : ['stun:stun.l.google.com:19302'];
        $turnUrls = $turnServer !== '' ? [$turnServer] : [];

        $statement = $this->db->prepare(
            'INSERT INTO webphone_settings
                (uuid, scope_key, company_id, sip_domain, wss_url, stun_urls, turn_urls, enable_webrtc, websocket_port, websocket_path, stun_server, turn_server, dtls_enabled, ice_enabled, notifications_enabled, session_timeout_minutes, status)
             VALUES
                (:uuid, :scope_key, :company_id, :sip_domain, :wss_url, :stun_urls, :turn_urls, :enable_webrtc, :websocket_port, :websocket_path, :stun_server, :turn_server, :dtls_enabled, :ice_enabled, :notifications_enabled, :session_timeout_minutes, "active")
             ON DUPLICATE KEY UPDATE
                sip_domain = VALUES(sip_domain),
                wss_url = VALUES(wss_url),
                stun_urls = VALUES(stun_urls),
                turn_urls = VALUES(turn_urls),
                enable_webrtc = VALUES(enable_webrtc),
                websocket_port = VALUES(websocket_port),
                websocket_path = VALUES(websocket_path),
                stun_server = VALUES(stun_server),
                turn_server = VALUES(turn_server),
                dtls_enabled = VALUES(dtls_enabled),
                ice_enabled = VALUES(ice_enabled),
                notifications_enabled = VALUES(notifications_enabled),
                session_timeout_minutes = VALUES(session_timeout_minutes),
                status = "active",
                deleted_at = NULL'
        );
        $statement->execute([
            'uuid' => uuid(),
            'scope_key' => $companyId === null ? 'global' : 'company:' . $companyId,
            'company_id' => $companyId,
            'sip_domain' => $sipDomain,
            'wss_url' => $wssUrl,
            'stun_urls' => json_encode($stunUrls, JSON_UNESCAPED_SLASHES),
            'turn_urls' => json_encode($turnUrls, JSON_UNESCAPED_SLASHES),
            'enable_webrtc' => $enableWebRtc,
            'websocket_port' => $port,
            'websocket_path' => $path,
            'stun_server' => $stunServer !== '' ? $stunServer : null,
            'turn_server' => $turnServer !== '' ? $turnServer : null,
            'dtls_enabled' => $dtlsEnabled,
            'ice_enabled' => $iceEnabled,
            'notifications_enabled' => $notifications,
            'session_timeout_minutes' => $sessionTimeout,
        ]);

        return $this->settings($companyId, $hostOverride);
    }

    public function issueSessionToken(int $userId, int $companyId, string $endpointId, int $minutes): array
    {
        $plain = 'uc200_webrtc_' . bin2hex(random_bytes(24));
        $expiresAt = gmdate('Y-m-d H:i:s', strtotime('+' . max(5, $minutes) . ' minutes'));

        $this->db->prepare(
            'INSERT INTO webrtc_session_tokens (uuid, user_id, company_id, endpoint_id, token_hash, expires_at)
             VALUES (:uuid, :user_id, :company_id, :endpoint_id, :token_hash, :expires_at)'
        )->execute([
            'uuid' => uuid(),
            'user_id' => $userId,
            'company_id' => $companyId,
            'endpoint_id' => $endpointId,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => $expiresAt,
        ]);

        return ['plain' => $plain, 'expires_at' => $expiresAt];
    }

    public function sessionByToken(?string $token): ?array
    {
        if ($token === null || trim($token) === '') {
            return null;
        }

        $statement = $this->db->prepare(
            'SELECT * FROM webrtc_session_tokens
             WHERE token_hash = :token_hash AND revoked_at IS NULL AND expires_at > UTC_TIMESTAMP()
             LIMIT 1'
        );
        $statement->execute(['token_hash' => hash('sha256', $token)]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function metrics(?int $companyId): array
    {
        $params = [];
        $where = '';
        if ($companyId !== null) {
            $where = ' AND company_id = :company_id';
            $params['company_id'] = $companyId;
        }

        $webrtcReady = $this->count('SELECT COUNT(*) FROM ps_endpoints WHERE deleted_at IS NULL AND webrtc = "yes"' . $where, $params);
        $connected = $this->count(
            'SELECT COUNT(DISTINCT endpoint_id) FROM webrtc_session_tokens WHERE revoked_at IS NULL AND expires_at > UTC_TIMESTAMP()' . $where,
            $params
        );
        $online = $this->count(
            'SELECT COUNT(*) FROM ps_endpoints WHERE deleted_at IS NULL AND sip_status IN ("registered", "reachable")' . $where,
            $params
        );
        $activeCalls = 0;
        try {
            $activeCalls = (new CallLogService($this->db))->dashboardMetrics($companyId)['active_calls'] ?? 0;
        } catch (\Throwable) {
            $activeCalls = 0;
        }

        return [
            'webrtc_ready' => $webrtcReady,
            'softphones_connected' => $connected,
            'extensions_online' => $online,
            'active_calls' => $activeCalls,
        ];
    }

    public function callerContext(int $companyId, string $number): ?array
    {
        $number = trim($number);
        if ($companyId <= 0 || $number === '') {
            return null;
        }

        $service = new CallLogService($this->db);
        $contact = $service->callerLookup($companyId, $number);
        if ($contact === null) {
            return null;
        }

        $contact['recent_calls'] = $service->contactCalls($companyId, (int) $contact['id'], 5);
        $contact['call_summary'] = $service->contactCallSummary($companyId, (int) $contact['id']);

        return $contact;
    }

    public function connectedForUser(int $userId, int $companyId, ?string $endpointId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM webrtc_session_tokens
                WHERE user_id = :user_id AND company_id = :company_id AND revoked_at IS NULL AND expires_at > UTC_TIMESTAMP()';
        $params = ['user_id' => $userId, 'company_id' => $companyId];
        if ($endpointId !== null && $endpointId !== '') {
            $sql .= ' AND endpoint_id = :endpoint_id';
            $params['endpoint_id'] = $endpointId;
        }

        return $this->count($sql, $params) > 0;
    }

    private function count(string $sql, array $params): int
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    private function normalizeList(mixed $value, array $fallback): array
    {
        $decoded = is_array($value) ? $value : json_decode((string) $value, true);
        if (! is_array($decoded) || $decoded === []) {
            return $fallback;
        }

        $items = array_values(array_filter(array_map(static fn ($item): string => trim((string) $item), $decoded)));

        return $items !== [] ? $items : $fallback;
    }

    private function yesNo(mixed $value): string
    {
        return in_array((string) $value, ['1', 'yes', 'true', 'on'], true) ? 'yes' : 'no';
    }
}
