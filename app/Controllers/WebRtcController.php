<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuditService;
use App\Services\ApiTokenService;
use App\Support\ApiResponse;

final class WebRtcController extends Controller
{
    public function softphone(Request $request): string
    {
        return view('webrtc/softphone', [
            'title' => 'Web Softphone',
            'extensions' => $this->extensionRows(),
            'settings' => $this->companySettings(),
            'preferences' => $this->userPreferences(),
            'favorites' => $this->favorites(),
            'recentCalls' => $this->recentCalls(),
            'presence' => $this->presenceRows(),
            'flash' => Session::flash('success'),
        ]);
    }

    public function bootstrap(Request $request): Response
    {
        if (! $this->isAuthenticated()) {
            return ApiResponse::error('unauthenticated', 'Session required.', 401);
        }

        if ($this->rateLimited('webrtc:bootstrap:' . (int) Session::get('user_id'), 30, 60)) {
            return ApiResponse::error('rate_limited', 'Too many WebRTC bootstrap requests.', 429);
        }

        $extensionUuid = (string) $request->input('extension', '');
        $extension = $this->extensionForSoftphone($extensionUuid);
        if ($extension === null) {
            return ApiResponse::error('not_found', 'No WebRTC extension available for this tenant.', 404);
        }

        $settings = $this->companySettings();
        $preferences = $this->upsertPreferences((string) $extension['uuid'], (int) $extension['company_id']);
        $token = $this->createSessionToken((int) $extension['company_id'], (string) $extension['id']);

        return ApiResponse::success([
            'token' => $token['plain'],
            'expires_at' => $token['expires_at'],
            'tenant' => [
                'company_id' => (int) $extension['company_id'],
                'company_name' => $extension['company_name'],
            ],
            'sip' => [
                'uri' => 'sip:' . $extension['auth_username'] . '@' . $settings['sip_domain'],
                'authorization_username' => $extension['auth_username'],
                'password' => $extension['auth_password'],
                'display_name' => $extension['extension_number'],
                'websocket_url' => $settings['wss_url'],
                'transport' => $extension['transport'] ?: 'transport-wss',
                'stun_servers' => $this->jsonList($settings['stun_urls']),
                'turn_servers' => $this->jsonList($settings['turn_urls']),
            ],
            'preferences' => $preferences,
            'presence' => $this->presenceRows(),
        ]);
    }

    public function preferences(Request $request): Response
    {
        if (! $this->isAuthenticated()) {
            return ApiResponse::error('unauthenticated', 'Session required.', 401);
        }

        $extension = $this->extensionForSoftphone((string) $request->input('extension', ''), false);
        $companyId = $extension !== null ? (int) $extension['company_id'] : (int) Session::get('company_id');
        if ($companyId <= 0) {
            return ApiResponse::error('tenant_required', 'A tenant extension is required.', 422);
        }

        $data = [
            'extension_uuid' => $extension['uuid'] ?? null,
            'microphone_id' => substr((string) $request->input('microphone_id', ''), 0, 255),
            'speaker_id' => substr((string) $request->input('speaker_id', ''), 0, 255),
            'camera_id' => substr((string) $request->input('camera_id', ''), 0, 255),
            'ringtone_volume' => max(0, min(100, (int) $request->input('ringtone_volume', 70))),
            'notifications_enabled' => (string) $request->input('notifications_enabled', 'yes') === 'yes' ? 'yes' : 'no',
            'auto_answer' => (string) $request->input('auto_answer', 'no') === 'yes' ? 'yes' : 'no',
            'default_presence' => $this->presenceValue((string) $request->input('default_presence', 'available')),
        ];

        $this->db()->prepare(
            'INSERT INTO webphone_user_preferences
                (uuid, user_id, company_id, extension_uuid, microphone_id, speaker_id, camera_id, ringtone_volume, notifications_enabled, auto_answer, default_presence)
             VALUES
                (:uuid, :user_id, :company_id, :extension_uuid, :microphone_id, :speaker_id, :camera_id, :ringtone_volume, :notifications_enabled, :auto_answer, :default_presence)
             ON DUPLICATE KEY UPDATE
                company_id = VALUES(company_id),
                extension_uuid = VALUES(extension_uuid),
                microphone_id = VALUES(microphone_id),
                speaker_id = VALUES(speaker_id),
                camera_id = VALUES(camera_id),
                ringtone_volume = VALUES(ringtone_volume),
                notifications_enabled = VALUES(notifications_enabled),
                auto_answer = VALUES(auto_answer),
                default_presence = VALUES(default_presence),
                deleted_at = NULL'
        )->execute(['uuid' => uuid(), 'user_id' => (int) Session::get('user_id'), 'company_id' => $companyId] + $data);

        return ApiResponse::success($data);
    }

    public function presence(Request $request): Response
    {
        if (! $this->isAuthenticated()) {
            return ApiResponse::error('unauthenticated', 'Session required.', 401);
        }

        $status = $this->presenceValue((string) $request->input('status', 'available'));
        $extensionUuid = (string) $request->input('extension', '');
        $extension = $this->extensionForSoftphone($extensionUuid);
        if ($extension === null) {
            return ApiResponse::error('not_found', 'Extension not found.', 404);
        }

        $this->db()->prepare(
            'INSERT INTO pbx_presence_states (uuid, company_id, endpoint_id, user_id, status, note)
             VALUES (:uuid, :company_id, :endpoint_id, :user_id, :status, :note)
             ON DUPLICATE KEY UPDATE status = VALUES(status), note = VALUES(note), user_id = VALUES(user_id), updated_at = CURRENT_TIMESTAMP, deleted_at = NULL'
        )->execute([
            'uuid' => uuid(),
            'company_id' => (int) $extension['company_id'],
            'endpoint_id' => (string) $extension['id'],
            'user_id' => (int) Session::get('user_id'),
            'status' => $status,
            'note' => substr((string) $request->input('note', ''), 0, 160) ?: null,
        ]);

        $this->db()->prepare('UPDATE ps_endpoints SET presence_status = :status WHERE id = :id')->execute([
            'status' => match ($status) {
                'available' => 'online',
                'dnd' => 'busy',
                default => $status,
            },
            'id' => (string) $extension['id'],
        ]);

        return ApiResponse::success(['status' => $status]);
    }

    public function event(Request $request): Response
    {
        $session = $this->eventSession($request);
        if ($session === null) {
            return ApiResponse::error('unauthenticated', 'WebRTC event token required.', 401);
        }

        if ($this->rateLimited('webrtc:event:' . (int) $session['id'], 240, 60)) {
            return ApiResponse::error('rate_limited', 'Too many WebRTC events.', 429);
        }

        $eventType = substr((string) $request->input('event_type', 'call.state'), 0, 80);
        $callId = substr((string) $request->input('call_id', ''), 0, 120);
        $payload = $request->json();

        $this->db()->prepare(
            'INSERT INTO webphone_call_events (uuid, company_id, user_id, endpoint_id, event_type, call_id, payload_json)
             VALUES (:uuid, :company_id, :user_id, :endpoint_id, :event_type, :call_id, :payload_json)'
        )->execute([
            'uuid' => uuid(),
            'company_id' => (int) $session['company_id'],
            'user_id' => (int) $session['user_id'],
            'endpoint_id' => (string) $session['endpoint_id'],
            'event_type' => $eventType,
            'call_id' => $callId !== '' ? $callId : null,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);

        if (in_array($eventType, ['call.ended', 'call.failed', 'call.missed'], true)) {
            $this->storeRecentCall($session, $payload, $eventType);
        }

        $this->syncPresenceFromEvent($session, $eventType);

        return ApiResponse::success(['recorded' => true]);
    }

    public function enableEndpoint(Request $request): void
    {
        $extensionUuid = (string) $request->input('id', '');
        $extension = $this->extensionForSoftphone($extensionUuid, false);
        if ($extension === null) {
            Session::flash('success', 'Extension no encontrada.');
            redirect('/softphone');
        }

        $this->db()->prepare(
            'UPDATE ps_endpoints
             SET transport = "transport-wss",
                 webrtc = "yes",
                 media_encryption = "dtls",
                 dtls_auto_generate_cert = "yes",
                 ice_support = "yes",
                 use_avpf = "yes",
                 rtcp_mux = "yes",
                 direct_media = "no",
                 force_rport = "yes",
                 rewrite_contact = "yes",
                 rtp_symmetric = "yes",
                 allow = "opus,ulaw,alaw"
             WHERE uuid = :uuid AND company_id = :company_id'
        )->execute(['uuid' => $extensionUuid, 'company_id' => (int) $extension['company_id']]);

        (new AuditService($this->db()))->record('webrtc.endpoint.enabled', 'ps_endpoints', null, [
            'endpoint' => $extension['id'],
        ], (int) $extension['company_id']);

        Session::flash('success', 'Extension preparada para WebRTC.');
        redirect('/softphone');
    }

    private function extensionRows(): array
    {
        $sql = 'SELECT e.*, c.name AS company_name
                FROM ps_endpoints e
                INNER JOIN companies c ON c.id = e.company_id
                WHERE e.deleted_at IS NULL';
        $params = [];
        if (! has_role('super-admin')) {
            $sql .= ' AND e.company_id = :company_id';
            $params['company_id'] = (int) Session::get('company_id');
        }
        $sql .= ' ORDER BY c.name, e.extension_number';
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function extensionForSoftphone(string $uuid = '', bool $requireWebRtc = true): ?array
    {
        $sql = 'SELECT e.*, c.name AS company_name
                FROM ps_endpoints e
                INNER JOIN companies c ON c.id = e.company_id
                WHERE e.deleted_at IS NULL AND e.status = "active"';
        $params = [];
        if ($uuid !== '') {
            $sql .= ' AND e.uuid = :uuid';
            $params['uuid'] = $uuid;
        }
        if ($requireWebRtc) {
            $sql .= ' AND e.webrtc = "yes"';
        }
        if (! has_role('super-admin')) {
            $sql .= ' AND e.company_id = :company_id';
            $params['company_id'] = (int) Session::get('company_id');
        }
        $sql .= ' ORDER BY e.extension_number LIMIT 1';
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    private function companySettings(): array
    {
        $companyId = (int) (Session::get('company_id') ?? 0);
        $statement = $this->db()->prepare(
            'SELECT * FROM webphone_settings WHERE (company_id = :company_id OR company_id IS NULL) AND deleted_at IS NULL ORDER BY company_id DESC LIMIT 1'
        );
        $statement->execute(['company_id' => $companyId]);
        $settings = $statement->fetch() ?: [];
        $host = $_SERVER['HTTP_HOST'] ?? env('APP_URL', 'localhost');
        $host = preg_replace('/:\d+$/', '', (string) $host);

        return [
            'sip_domain' => $settings['sip_domain'] ?? $host,
            'wss_url' => $settings['wss_url'] ?? 'wss://' . $host . ':8089/ws',
            'stun_urls' => $settings['stun_urls'] ?? '["stun:stun.l.google.com:19302"]',
            'turn_urls' => $settings['turn_urls'] ?? '[]',
            'notifications_enabled' => $settings['notifications_enabled'] ?? 'yes',
            'session_timeout_minutes' => (int) ($settings['session_timeout_minutes'] ?? 480),
        ];
    }

    private function userPreferences(): array
    {
        $statement = $this->db()->prepare(
            'SELECT * FROM webphone_user_preferences WHERE user_id = :user_id AND deleted_at IS NULL LIMIT 1'
        );
        $statement->execute(['user_id' => (int) Session::get('user_id')]);

        return $statement->fetch() ?: [];
    }

    private function upsertPreferences(string $extensionUuid, int $companyId): array
    {
        $preferences = $this->userPreferences();
        if ($preferences !== []) {
            $this->db()->prepare('UPDATE webphone_user_preferences SET company_id = :company_id, extension_uuid = :extension_uuid WHERE id = :id')
                ->execute(['company_id' => $companyId, 'extension_uuid' => $extensionUuid, 'id' => (int) $preferences['id']]);
            $preferences['company_id'] = $companyId;
            $preferences['extension_uuid'] = $extensionUuid;
            return $preferences;
        }

        $this->db()->prepare(
            'INSERT INTO webphone_user_preferences (uuid, user_id, company_id, extension_uuid)
             VALUES (:uuid, :user_id, :company_id, :extension_uuid)'
        )->execute([
            'uuid' => uuid(),
            'user_id' => (int) Session::get('user_id'),
            'company_id' => $companyId,
            'extension_uuid' => $extensionUuid,
        ]);

        return $this->userPreferences();
    }

    private function favorites(): array
    {
        $statement = $this->db()->prepare(
            'SELECT f.*, e.extension_number, e.presence_status
             FROM webphone_favorites f
             INNER JOIN ps_endpoints e ON e.id = f.endpoint_id
             WHERE f.user_id = :user_id AND f.deleted_at IS NULL
             ORDER BY f.sort_order, f.label'
        );
        $statement->execute(['user_id' => (int) Session::get('user_id')]);

        return $statement->fetchAll();
    }

    private function recentCalls(): array
    {
        $statement = $this->db()->prepare(
            'SELECT * FROM webphone_recent_calls WHERE user_id = :user_id AND deleted_at IS NULL ORDER BY started_at DESC LIMIT 20'
        );
        $statement->execute(['user_id' => (int) Session::get('user_id')]);

        return $statement->fetchAll();
    }

    private function presenceRows(): array
    {
        $companyId = (int) (Session::get('company_id') ?? 0);
        $sql = 'SELECT e.uuid, e.id AS endpoint_id, e.extension_number, COALESCE(p.status, e.presence_status, "unknown") AS status
                FROM ps_endpoints e
                LEFT JOIN pbx_presence_states p ON p.endpoint_id = e.id AND p.deleted_at IS NULL
                WHERE e.deleted_at IS NULL';
        $params = [];
        if (! has_role('super-admin')) {
            $sql .= ' AND e.company_id = :company_id';
            $params['company_id'] = $companyId;
        }
        $sql .= ' ORDER BY e.extension_number LIMIT 100';
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function createSessionToken(int $companyId, string $endpointId): array
    {
        $settings = $this->companySettings();
        $minutes = max(5, (int) $settings['session_timeout_minutes']);
        $plain = 'uc200_webrtc_' . bin2hex(random_bytes(24));
        $expiresAt = gmdate('Y-m-d H:i:s', strtotime('+' . $minutes . ' minutes'));
        $this->db()->prepare(
            'INSERT INTO webrtc_session_tokens (uuid, user_id, company_id, endpoint_id, token_hash, expires_at)
             VALUES (:uuid, :user_id, :company_id, :endpoint_id, :token_hash, :expires_at)'
        )->execute([
            'uuid' => uuid(),
            'user_id' => (int) Session::get('user_id'),
            'company_id' => $companyId,
            'endpoint_id' => $endpointId,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => $expiresAt,
        ]);

        return ['plain' => $plain, 'expires_at' => $expiresAt];
    }

    private function eventSession(Request $request): ?array
    {
        $token = $request->bearerToken();
        if ($token === null) {
            return null;
        }

        $statement = $this->db()->prepare(
            'SELECT * FROM webrtc_session_tokens
             WHERE token_hash = :token_hash AND revoked_at IS NULL AND expires_at > UTC_TIMESTAMP() LIMIT 1'
        );
        $statement->execute(['token_hash' => hash('sha256', $token)]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    private function storeRecentCall(array $session, array $payload, string $eventType): void
    {
        $this->db()->prepare(
            'INSERT INTO webphone_recent_calls (uuid, company_id, user_id, endpoint_id, direction, remote_number, disposition, started_at, ended_at, duration_seconds, call_id)
             VALUES (:uuid, :company_id, :user_id, :endpoint_id, :direction, :remote_number, :disposition, :started_at, :ended_at, :duration_seconds, :call_id)'
        )->execute([
            'uuid' => uuid(),
            'company_id' => (int) $session['company_id'],
            'user_id' => (int) $session['user_id'],
            'endpoint_id' => (string) $session['endpoint_id'],
            'direction' => in_array(($payload['direction'] ?? ''), ['inbound', 'outbound'], true) ? $payload['direction'] : 'outbound',
            'remote_number' => substr((string) ($payload['remote_number'] ?? ''), 0, 80),
            'disposition' => $eventType === 'call.ended' ? 'answered' : str_replace('call.', '', $eventType),
            'started_at' => $payload['started_at'] ?? gmdate('Y-m-d H:i:s'),
            'ended_at' => gmdate('Y-m-d H:i:s'),
            'duration_seconds' => max(0, (int) ($payload['duration_seconds'] ?? 0)),
            'call_id' => substr((string) ($payload['call_id'] ?? ''), 0, 120) ?: null,
        ]);
    }

    private function syncPresenceFromEvent(array $session, string $eventType): void
    {
        $status = match ($eventType) {
            'call.created', 'call.ringing' => 'ringing',
            'call.answered', 'call.hold', 'call.transfer' => 'busy',
            'call.ended', 'call.failed', 'call.missed', 'unregister' => 'available',
            'register' => 'available',
            default => null,
        };

        if ($status === null) {
            return;
        }

        $this->db()->prepare(
            'INSERT INTO pbx_presence_states (uuid, company_id, endpoint_id, user_id, status, note)
             VALUES (:uuid, :company_id, :endpoint_id, :user_id, :status, NULL)
             ON DUPLICATE KEY UPDATE status = VALUES(status), user_id = VALUES(user_id), updated_at = CURRENT_TIMESTAMP, deleted_at = NULL'
        )->execute([
            'uuid' => uuid(),
            'company_id' => (int) $session['company_id'],
            'endpoint_id' => (string) $session['endpoint_id'],
            'user_id' => (int) $session['user_id'],
            'status' => $status,
        ]);

        $this->db()->prepare('UPDATE ps_endpoints SET presence_status = :status WHERE id = :id')->execute([
            'status' => match ($status) {
                'available' => 'online',
                default => $status,
            },
            'id' => (string) $session['endpoint_id'],
        ]);
    }

    private function jsonList(?string $json): array
    {
        $decoded = json_decode((string) $json, true);

        return is_array($decoded) ? array_values(array_filter(array_map('strval', $decoded))) : [];
    }

    private function presenceValue(string $value): string
    {
        return in_array($value, ['available', 'busy', 'ringing', 'away', 'dnd', 'offline'], true) ? $value : 'available';
    }

    private function rateLimited(string $key, int $limit, int $windowSeconds): bool
    {
        try {
            return (new ApiTokenService($this->db()))->hitRateLimit($key, $limit, $windowSeconds);
        } catch (\Throwable) {
            return false;
        }
    }

    private function isAuthenticated(): bool
    {
        return Session::get('user_id') !== null;
    }
}
