<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuditService;
use App\Support\ApiResponse;
use PDO;

final class QueueController extends Controller
{
    public function index(Request $request): string
    {
        if (! $this->tableExists('pbx_queues')) {
            return view('pbx/setup', [
                'title' => 'Call Center',
                'missingTables' => ['pbx_queues', 'pbx_queue_members', 'pbx_queue_events'],
            ]);
        }

        return view('queues/index', [
            'title' => 'Call Center',
            'flash' => Session::flash('success'),
            'queues' => $this->queues(),
            'agents' => $this->agents(),
            'pauseReasons' => $this->pauseReasons(),
            'extensions' => $this->extensions(),
            'wallboard' => $this->wallboardRows(),
            'reports' => $this->reportRows(),
        ]);
    }

    public function create(Request $request): string
    {
        return view('queues/form', [
            'title' => 'Nueva queue',
            'queue' => [],
            'companies' => $this->companies(),
            'errors' => Session::flash('errors') ?? [],
            'old' => Session::flash('old') ?? [],
            'action' => '/call-center/queues/store',
            'mode' => 'create',
        ]);
    }

    public function edit(Request $request): string
    {
        $queue = $this->queueFromRequest($request);

        return view('queues/form', [
            'title' => 'Editar queue',
            'queue' => $queue,
            'companies' => $this->companies(),
            'errors' => Session::flash('errors') ?? [],
            'old' => Session::flash('old') ?? [],
            'action' => '/call-center/queues/update',
            'mode' => 'edit',
        ]);
    }

    public function store(Request $request): void
    {
        $data = $this->queuePayload($request);
        $errors = $this->validateQueue($data);
        if ($errors !== []) {
            $this->back('/call-center/queues/create', $errors, $data);
        }
        if ($this->queueExtensionExists((int) $data['company_id'], $data['extension'])) {
            $this->back('/call-center/queues/create', ['extension' => 'La extension de queue ya existe para este tenant.'], $data);
        }

        $this->db()->prepare(
            'INSERT INTO pbx_queues
                (uuid, company_id, name, extension, strategy, timeout_seconds, retry_seconds, wrapup_seconds, max_callers,
                 music_on_hold, announce_position, announce_hold_time, service_level_seconds,
                 overflow_destination_type, overflow_destination_id, failover_destination_type, failover_destination_id, recording_enabled, status)
             VALUES
                (:uuid, :company_id, :name, :extension, :strategy, :timeout_seconds, :retry_seconds, :wrapup_seconds, :max_callers,
                 :music_on_hold, :announce_position, :announce_hold_time, :service_level_seconds,
                 :overflow_destination_type, :overflow_destination_id, :failover_destination_type, :failover_destination_id, :recording_enabled, :status)'
        )->execute(['uuid' => uuid()] + $data);

        (new AuditService($this->db()))->record('queue.created', 'pbx_queues', null, [
            'extension' => $data['extension'],
            'name' => $data['name'],
        ], (int) $data['company_id']);

        Session::flash('success', 'Queue creada correctamente.');
        redirect('/call-center');
    }

    public function update(Request $request): void
    {
        $queue = $this->queueFromRequest($request);
        $data = $this->queuePayload($request);
        $data['company_id'] = (int) $queue['company_id'];
        $errors = $this->validateQueue($data);
        if ($errors !== []) {
            $this->back('/call-center/queues/edit?id=' . $queue['uuid'], $errors, $data);
        }
        if ($this->queueExtensionExists((int) $queue['company_id'], $data['extension'], (string) $queue['uuid'])) {
            $this->back('/call-center/queues/edit?id=' . $queue['uuid'], ['extension' => 'La extension de queue ya existe para este tenant.'], $data);
        }

        $this->db()->prepare(
            'UPDATE pbx_queues
             SET name = :name, extension = :extension, strategy = :strategy, timeout_seconds = :timeout_seconds,
                 retry_seconds = :retry_seconds, wrapup_seconds = :wrapup_seconds, max_callers = :max_callers,
                 music_on_hold = :music_on_hold, announce_position = :announce_position,
                 announce_hold_time = :announce_hold_time, service_level_seconds = :service_level_seconds,
                 overflow_destination_type = :overflow_destination_type, overflow_destination_id = :overflow_destination_id,
                 failover_destination_type = :failover_destination_type, failover_destination_id = :failover_destination_id,
                 recording_enabled = :recording_enabled, status = :status
             WHERE uuid = :uuid AND company_id = :company_id'
        )->execute(['uuid' => $queue['uuid']] + $data);

        (new AuditService($this->db()))->record('queue.updated', 'pbx_queues', null, [
            'extension' => $data['extension'],
            'name' => $data['name'],
        ], (int) $queue['company_id']);

        Session::flash('success', 'Queue actualizada correctamente.');
        redirect('/call-center');
    }

    public function destroy(Request $request): void
    {
        $queue = $this->queueFromRequest($request);
        $this->db()->prepare('UPDATE pbx_queues SET deleted_at = NOW(), status = "inactive" WHERE uuid = :uuid AND company_id = :company_id')
            ->execute(['uuid' => $queue['uuid'], 'company_id' => (int) $queue['company_id']]);
        Session::flash('success', 'Queue eliminada.');
        redirect('/call-center');
    }

    public function storeAgent(Request $request): void
    {
        $queue = $this->queueFromRequest($request, 'queue_id');
        $endpointId = substr((string) $request->input('endpoint_id', ''), 0, 80);
        if ($endpointId === '' || ! $this->endpointOwned($endpointId, (int) $queue['company_id'])) {
            $this->back('/call-center', ['agent' => 'Extension invalida para este tenant.'], []);
        }
        if ($this->queueMemberExists((int) $queue['id'], $endpointId)) {
            $this->back('/call-center', ['agent' => 'El agente ya pertenece a esta queue.'], []);
        }

        $this->db()->prepare(
            'INSERT INTO pbx_queue_members (uuid, company_id, queue_id, endpoint_id, member_name, penalty, dynamic, status)
             VALUES (:uuid, :company_id, :queue_id, :endpoint_id, :member_name, :penalty, "yes", "active")
             ON DUPLICATE KEY UPDATE penalty = VALUES(penalty), member_name = VALUES(member_name), status = "active", deleted_at = NULL'
        )->execute([
            'uuid' => uuid(),
            'company_id' => (int) $queue['company_id'],
            'queue_id' => (int) $queue['id'],
            'endpoint_id' => $endpointId,
            'member_name' => substr((string) $request->input('member_name', ''), 0, 120) ?: null,
            'penalty' => max(0, min(99, (int) $request->input('penalty', 0))),
        ]);

        $this->event((int) $queue['company_id'], (int) $queue['id'], $endpointId, 'agent.added');
        Session::flash('success', 'Agente agregado a la queue.');
        redirect('/call-center');
    }

    public function agentAction(Request $request): void
    {
        $member = $this->memberFromRequest($request);
        $action = (string) $request->input('action', 'login');
        $reason = substr((string) $request->input('reason', ''), 0, 120) ?: null;
        $state = match ($action) {
            'logout' => 'offline',
            'pause' => 'paused',
            'unpause', 'login' => 'online',
            default => 'online',
        };

        $this->db()->prepare(
            'UPDATE pbx_queue_members
             SET paused = :paused, pause_reason = :reason,
                 last_login_at = IF(:action IN ("login", "unpause"), NOW(), last_login_at),
                 last_logout_at = IF(:action = "logout", NOW(), last_logout_at)
             WHERE uuid = :uuid AND company_id = :company_id'
        )->execute([
            'paused' => $state === 'paused' ? 'yes' : 'no',
            'reason' => $state === 'paused' ? $reason : null,
            'action' => $action,
            'uuid' => $member['uuid'],
            'company_id' => (int) $member['company_id'],
        ]);

        $this->db()->prepare(
            'INSERT INTO pbx_queue_agent_states (uuid, company_id, queue_id, endpoint_id, state, pause_reason, last_state_at)
             VALUES (:uuid, :company_id, :queue_id, :endpoint_id, :state, :pause_reason, NOW())
             ON DUPLICATE KEY UPDATE state = VALUES(state), pause_reason = VALUES(pause_reason), last_state_at = NOW(), deleted_at = NULL'
        )->execute([
            'uuid' => uuid(),
            'company_id' => (int) $member['company_id'],
            'queue_id' => (int) $member['queue_id'],
            'endpoint_id' => (string) $member['endpoint_id'],
            'state' => $state,
            'pause_reason' => $state === 'paused' ? $reason : null,
        ]);

        $this->event((int) $member['company_id'], (int) $member['queue_id'], (string) $member['endpoint_id'], 'agent.' . $action, ['reason' => $reason]);
        Session::flash('success', 'Estado de agente actualizado.');
        redirect('/call-center');
    }

    public function wallboard(Request $request): Response
    {
        return ApiResponse::success([
            'queues' => $this->wallboardRows(),
            'agents' => $this->agents(),
            'reports' => $this->reportRows(),
        ]);
    }

    private function queuePayload(Request $request): array
    {
        return [
            'company_id' => $this->tenantId((int) $request->input('company_id', 0)),
            'name' => substr(trim((string) $request->input('name', '')), 0, 120),
            'extension' => substr(preg_replace('/\D+/', '', (string) $request->input('extension', '')) ?: '', 0, 20),
            'strategy' => $this->option((string) $request->input('strategy', 'ringall'), ['ringall', 'leastrecent', 'fewestcalls', 'random', 'rrmemory', 'linear'], 'ringall'),
            'timeout_seconds' => max(5, min(300, (int) $request->input('timeout_seconds', 20))),
            'retry_seconds' => max(1, min(120, (int) $request->input('retry_seconds', 5))),
            'wrapup_seconds' => max(0, min(600, (int) $request->input('wrapup_seconds', 0))),
            'max_callers' => max(0, min(1000, (int) $request->input('max_callers', 0))),
            'music_on_hold' => substr(trim((string) $request->input('music_on_hold', '')), 0, 80) ?: null,
            'announce_position' => (string) $request->input('announce_position', 'yes') === 'yes' ? 'yes' : 'no',
            'announce_hold_time' => (string) $request->input('announce_hold_time', 'no') === 'yes' ? 'yes' : 'no',
            'service_level_seconds' => max(5, min(3600, (int) $request->input('service_level_seconds', 60))),
            'overflow_destination_type' => $this->destinationType((string) $request->input('overflow_destination_type', 'hangup')),
            'overflow_destination_id' => substr(trim((string) $request->input('overflow_destination_id', '')), 0, 80) ?: null,
            'failover_destination_type' => $this->destinationType((string) $request->input('failover_destination_type', 'hangup')),
            'failover_destination_id' => substr(trim((string) $request->input('failover_destination_id', '')), 0, 80) ?: null,
            'recording_enabled' => (string) $request->input('recording_enabled', 'no') === 'yes' ? 'yes' : 'no',
            'status' => (string) $request->input('status', 'active') === 'inactive' ? 'inactive' : 'active',
        ];
    }

    private function validateQueue(array $data): array
    {
        $errors = [];
        if ($data['name'] === '') {
            $errors['name'] = 'Nombre requerido.';
        }
        if ($data['extension'] === '') {
            $errors['extension'] = 'Extension requerida.';
        }
        if ((int) $data['company_id'] <= 0) {
            $errors['company'] = 'Tenant requerido.';
        }

        return $errors;
    }

    private function queues(): array
    {
        $sql = 'SELECT q.*, c.name AS company_name,
                       (SELECT COUNT(*) FROM pbx_queue_members m WHERE m.queue_id = q.id AND m.deleted_at IS NULL) AS members_count
                FROM pbx_queues q
                INNER JOIN companies c ON c.id = q.company_id
                WHERE q.deleted_at IS NULL';
        $params = [];
        if (! has_role('super-admin')) {
            $sql .= ' AND q.company_id = :company_id';
            $params['company_id'] = (int) Session::get('company_id');
        }
        $sql .= ' ORDER BY c.name, q.extension';
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function agents(): array
    {
        $sql = 'SELECT m.*, q.name AS queue_name, q.extension AS queue_extension, e.extension_number,
                       COALESCE(s.state, IF(m.paused = "yes", "paused", "offline")) AS state,
                       COALESCE(s.pause_reason, m.pause_reason) AS current_pause_reason
                FROM pbx_queue_members m
                INNER JOIN pbx_queues q ON q.id = m.queue_id
                LEFT JOIN ps_endpoints e ON e.id = m.endpoint_id
                LEFT JOIN pbx_queue_agent_states s ON s.queue_id = m.queue_id AND s.endpoint_id = m.endpoint_id
                WHERE m.deleted_at IS NULL AND q.deleted_at IS NULL';
        $params = [];
        if (! has_role('super-admin')) {
            $sql .= ' AND m.company_id = :company_id';
            $params['company_id'] = (int) Session::get('company_id');
        }
        $sql .= ' ORDER BY q.extension, e.extension_number';
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function wallboardRows(): array
    {
        $sql = 'SELECT q.uuid, q.company_id, q.name, q.extension, q.strategy, q.service_level_seconds, q.status,
                       (SELECT COUNT(*) FROM pbx_queue_agent_states s WHERE s.queue_id = q.id AND s.deleted_at IS NULL AND s.state IN ("online", "ringing", "in_call", "wrapup")) AS agents_online,
                       (SELECT COUNT(*) FROM pbx_queue_agent_states s WHERE s.queue_id = q.id AND s.deleted_at IS NULL AND s.state = "paused") AS agents_paused,
                       (SELECT COUNT(*) FROM pbx_queue_agent_states s WHERE s.queue_id = q.id AND s.deleted_at IS NULL AND s.state = "in_call") AS active_calls,
                       COALESCE(today.waiting_calls, 0) AS waiting_calls,
                       COALESCE(today.abandoned_calls, 0) AS abandoned_calls,
                       COALESCE(today.service_level_percent, 0) AS service_level_percent,
                       COALESCE(today.avg_hold_seconds, 0) AS avg_hold_seconds,
                       COALESCE(today.avg_talk_seconds, 0) AS avg_talk_seconds
                FROM pbx_queues q
                LEFT JOIN pbx_queue_metrics today ON today.queue_id = q.id AND today.metric_date = CURRENT_DATE AND today.deleted_at IS NULL
                WHERE q.deleted_at IS NULL';
        $params = [];
        if (! has_role('super-admin')) {
            $sql .= ' AND q.company_id = :company_id';
            $params['company_id'] = (int) Session::get('company_id');
        }
        $sql .= ' ORDER BY q.extension';
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function reportRows(): array
    {
        $sql = 'SELECT q.name, q.extension,
                       COALESCE(SUM(m.offered_calls), 0) AS offered_calls,
                       COALESCE(SUM(m.answered_calls), 0) AS answered_calls,
                       COALESCE(SUM(m.abandoned_calls), 0) AS abandoned_calls,
                       COALESCE(ROUND(AVG(NULLIF(m.service_level_percent, 0)), 2), 0) AS sla,
                       COALESCE(ROUND(AVG(NULLIF(m.avg_hold_seconds, 0))), 0) AS avg_hold,
                       COALESCE(ROUND(AVG(NULLIF(m.avg_talk_seconds, 0))), 0) AS avg_talk
                FROM pbx_queues q
                LEFT JOIN pbx_queue_metrics m ON m.queue_id = q.id AND m.metric_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
                WHERE q.deleted_at IS NULL';
        $params = [];
        if (! has_role('super-admin')) {
            $sql .= ' AND q.company_id = :company_id';
            $params['company_id'] = (int) Session::get('company_id');
        }
        $sql .= ' GROUP BY q.id, q.name, q.extension ORDER BY q.extension';
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function extensions(): array
    {
        $sql = 'SELECT id, extension_number, company_id FROM ps_endpoints WHERE deleted_at IS NULL AND status = "active"';
        $params = [];
        if (! has_role('super-admin')) {
            $sql .= ' AND company_id = :company_id';
            $params['company_id'] = (int) Session::get('company_id');
        }
        $sql .= ' ORDER BY extension_number';
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function companies(): array
    {
        if (! has_role('super-admin')) {
            $statement = $this->db()->prepare('SELECT id, name FROM companies WHERE id = :id AND deleted_at IS NULL');
            $statement->execute(['id' => (int) Session::get('company_id')]);
            return $statement->fetchAll();
        }

        return $this->db()->query('SELECT id, name FROM companies WHERE deleted_at IS NULL ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    }

    private function pauseReasons(): array
    {
        $sql = 'SELECT * FROM pbx_queue_pause_reasons WHERE deleted_at IS NULL AND status = "active"';
        $params = [];
        if (! has_role('super-admin')) {
            $sql .= ' AND company_id = :company_id';
            $params['company_id'] = (int) Session::get('company_id');
        }
        $sql .= ' ORDER BY name';
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function queueFromRequest(Request $request, string $key = 'id'): array
    {
        $id = (string) $request->input($key, '');
        $sql = 'SELECT * FROM pbx_queues WHERE uuid = :uuid AND deleted_at IS NULL';
        $params = ['uuid' => $id];
        if (! has_role('super-admin')) {
            $sql .= ' AND company_id = :company_id';
            $params['company_id'] = (int) Session::get('company_id');
        }
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);
        $queue = $statement->fetch();
        if (! is_array($queue)) {
            redirect('/call-center');
        }

        return $queue;
    }

    private function memberFromRequest(Request $request): array
    {
        $sql = 'SELECT * FROM pbx_queue_members WHERE uuid = :uuid AND deleted_at IS NULL';
        $params = ['uuid' => (string) $request->input('id', '')];
        if (! has_role('super-admin')) {
            $sql .= ' AND company_id = :company_id';
            $params['company_id'] = (int) Session::get('company_id');
        }
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);
        $member = $statement->fetch();
        if (! is_array($member)) {
            redirect('/call-center');
        }

        return $member;
    }

    private function endpointOwned(string $endpointId, int $companyId): bool
    {
        $statement = $this->db()->prepare('SELECT COUNT(*) FROM ps_endpoints WHERE id = :id AND company_id = :company_id AND deleted_at IS NULL');
        $statement->execute(['id' => $endpointId, 'company_id' => $companyId]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function queueExtensionExists(int $companyId, string $extension, string $exceptUuid = ''): bool
    {
        $sql = 'SELECT COUNT(*) FROM pbx_queues WHERE company_id = :company_id AND extension = :extension AND deleted_at IS NULL';
        $params = ['company_id' => $companyId, 'extension' => $extension];
        if ($exceptUuid !== '') {
            $sql .= ' AND uuid <> :uuid';
            $params['uuid'] = $exceptUuid;
        }
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }

    private function queueMemberExists(int $queueId, string $endpointId): bool
    {
        $statement = $this->db()->prepare(
            'SELECT COUNT(*) FROM pbx_queue_members WHERE queue_id = :queue_id AND endpoint_id = :endpoint_id AND deleted_at IS NULL'
        );
        $statement->execute(['queue_id' => $queueId, 'endpoint_id' => $endpointId]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function event(int $companyId, ?int $queueId, ?string $endpointId, string $eventType, array $payload = []): void
    {
        $this->db()->prepare(
            'INSERT INTO pbx_queue_events (uuid, company_id, queue_id, endpoint_id, event_type, payload_json, occurred_at)
             VALUES (:uuid, :company_id, :queue_id, :endpoint_id, :event_type, :payload_json, NOW())'
        )->execute([
            'uuid' => uuid(),
            'company_id' => $companyId,
            'queue_id' => $queueId,
            'endpoint_id' => $endpointId,
            'event_type' => $eventType,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function tenantId(int $requested): int
    {
        if (has_role('super-admin') && $requested > 0) {
            return $requested;
        }

        return (int) Session::get('company_id');
    }

    private function option(string $value, array $allowed, string $fallback): string
    {
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private function destinationType(string $value): string
    {
        return $this->option($value, ['extension', 'ringgroup', 'ivr', 'voicemail', 'queue', 'hangup'], 'hangup');
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->db()->prepare(
            'SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = :table'
        );
        $statement->execute(['table' => $table]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function back(string $url, array $errors, array $old): void
    {
        Session::flash('errors', $errors);
        Session::flash('old', $old);
        redirect($url);
    }
}
