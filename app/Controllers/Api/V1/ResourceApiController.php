<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\ApiQueryService;
use App\Services\ApiTokenService;
use App\Support\ApiResponse;

final class ResourceApiController extends Controller
{
    public function companies(Request $request): Response { return $this->index($request, 'companies'); }
    public function users(Request $request): Response { return $this->index($request, 'users'); }
    public function extensions(Request $request): Response { return $this->index($request, 'extensions'); }
    public function ringgroups(Request $request): Response { return $this->index($request, 'ringgroups'); }
    public function ivr(Request $request): Response { return $this->index($request, 'ivr'); }
    public function trunks(Request $request): Response { return $this->index($request, 'trunks'); }
    public function recordings(Request $request): Response { return $this->index($request, 'recordings'); }
    public function provisioningDevices(Request $request): Response { return $this->index($request, 'provisioning_devices'); }
    public function provisioningTemplates(Request $request): Response { return $this->index($request, 'provisioning_templates'); }
    public function provisioningPhonebooks(Request $request): Response { return $this->index($request, 'provisioning_phonebooks'); }
    public function crmContacts(Request $request): Response { return $this->index($request, 'crm_contacts'); }
    public function crmAccounts(Request $request): Response { return $this->index($request, 'crm_accounts'); }
    public function crmActivities(Request $request): Response { return $this->index($request, 'crm_activities'); }
    public function crmCalls(Request $request): Response { return $this->index($request, 'crm_calls'); }
    public function queues(Request $request): Response { return $this->index($request, 'queues'); }
    public function queueAgents(Request $request): Response { return $this->index($request, 'queue_agents'); }
    public function queueEvents(Request $request): Response { return $this->index($request, 'queue_events'); }
    public function queueMetrics(Request $request): Response { return $this->index($request, 'queue_metrics'); }
    public function resellers(Request $request): Response { return $this->index($request, 'resellers'); }
    public function billingSubscriptions(Request $request): Response { return $this->index($request, 'billing_subscriptions'); }
    public function billingInvoices(Request $request): Response { return $this->index($request, 'billing_invoices'); }
    public function billingUsage(Request $request): Response { return $this->index($request, 'billing_usage'); }

    private function index(Request $request, string $resource): Response
    {
        $token = $this->requireToken($request, $resource . ':read');
        if ($token instanceof Response) {
            return $token;
        }

        $config = $this->resources()[$resource] ?? null;
        if ($config === null) {
            return ApiResponse::error('not_found', 'Resource not found.', 404);
        }

        $roles = $this->rolesForUser((int) $token['user_id']);
        $superAdmin = in_array('super-admin', $roles, true);
        $result = (new ApiQueryService($this->db()))->paginate($config, $_GET, $token['company_id'] !== null ? (int) $token['company_id'] : null, $superAdmin);

        return ApiResponse::success($result['items'], $result['meta']);
    }

    private function requireToken(Request $request, string $scope): array|Response
    {
        $service = new ApiTokenService($this->db());
        $token = $service->authenticate($request);

        if ($token === null) {
            return ApiResponse::error('unauthenticated', 'Bearer token required.', 401);
        }

        if ($service->hitRateLimit('token:' . (string) $token['id'], 120, 60)) {
            return ApiResponse::error('rate_limited', 'Too many requests.', 429);
        }

        if (! $service->can($token, $scope)) {
            return ApiResponse::error('forbidden', 'Missing required scope: ' . $scope, 403);
        }

        return $token;
    }

    private function rolesForUser(int $userId): array
    {
        $statement = $this->db()->prepare(
            'SELECT r.slug
             FROM user_roles ur
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = :user_id AND r.deleted_at IS NULL'
        );
        $statement->execute(['user_id' => $userId]);

        return array_map('strval', $statement->fetchAll(\PDO::FETCH_COLUMN));
    }

    private function resources(): array
    {
        return [
            'companies' => [
                'alias' => 'c',
                'from' => 'companies c',
                'tenant' => true,
                'tenant_column' => 'c.id',
                'select' => ['c.uuid', 'c.name', 'c.legal_name', 'c.tax_id', 'c.status', 'c.locale', 'c.created_at', 'c.updated_at'],
                'search' => ['c.name', 'c.legal_name', 'c.tax_id'],
                'filterable' => ['status'],
                'sortable' => ['c.name', 'c.status', 'c.created_at', 'c.updated_at'],
                'default_sort' => 'c.created_at',
            ],
            'users' => [
                'alias' => 'u',
                'from' => 'users u LEFT JOIN companies c ON c.id = u.company_id',
                'tenant' => true,
                'select' => ['u.uuid', 'u.company_id', 'c.name AS company_name', 'u.name', 'u.email', 'u.username', 'u.external_provider', 'u.sync_enabled', 'u.last_synced_at', 'u.is_active', 'u.locale', 'u.last_login_at', 'u.created_at', 'u.updated_at'],
                'search' => ['u.name', 'u.email'],
                'filterable' => [],
                'sortable' => ['u.name', 'u.email', 'u.created_at', 'u.updated_at'],
                'default_sort' => 'u.created_at',
            ],
            'extensions' => [
                'alias' => 'e',
                'from' => 'ps_endpoints e INNER JOIN companies c ON c.id = e.company_id',
                'tenant' => true,
                'select' => ['e.uuid', 'e.company_id', 'c.name AS company_name', 'e.id AS sip_username', 'e.extension_number', 'e.display_name', 'e.email', 'e.contact_email', 'e.device_type', 'e.auth_username', 'e.context', 'e.allow', 'e.direct_media', 'e.recording_enabled', 'e.voicemail_enabled', 'e.external_provider', 'e.sync_enabled', 'e.last_synced_at', 'e.status', 'e.sip_status', 'e.presence_status', 'e.created_at', 'e.updated_at'],
                'search' => ['e.extension_number', 'e.id', 'e.auth_username', 'e.email'],
                'filterable' => ['status'],
                'sortable' => ['e.extension_number', 'e.status', 'e.created_at', 'e.updated_at'],
                'default_sort' => 'e.extension_number',
            ],
            'ringgroups' => $this->flowResource('pbx_ring_groups', 'g', ['g.uuid', 'g.company_id', 'c.name AS company_name', 'g.name', 'g.extension', 'g.strategy', 'g.timeout_seconds', 'g.members', 'g.failover_destination_type', 'g.failover_destination_id', 'g.status', 'g.created_at', 'g.updated_at'], ['g.name', 'g.extension']),
            'ivr' => $this->flowResource('pbx_ivrs', 'i', ['i.uuid', 'i.company_id', 'c.name AS company_name', 'i.name', 'i.extension', 'i.prompt_file', 'i.digit_timeout', 'i.invalid_retries', 'i.options_json', 'i.failover_destination_type', 'i.failover_destination_id', 'i.status', 'i.created_at', 'i.updated_at'], ['i.name', 'i.extension']),
            'trunks' => $this->flowResource('pbx_sip_trunks', 't', ['t.uuid', 't.company_id', 'c.name AS company_name', 't.name', 't.host', 't.username', 't.transport', 't.codecs', 't.qualify_frequency', 't.outbound_registration', 't.inbound_auth', 't.nat_mode', 't.status', 't.created_at', 't.updated_at'], ['t.name', 't.host', 't.username']),
            'recordings' => [
                'alias' => 'r',
                'from' => 'pbx_recordings r INNER JOIN companies c ON c.id = r.company_id',
                'tenant' => true,
                'select' => ['r.uuid', 'r.company_id', 'c.name AS company_name', 'r.direction', 'r.caller', 'r.callee', 'r.started_at', 'r.ended_at', 'r.duration_seconds', 'r.file_path', 'r.uniqueid', 'r.status', 'r.created_at'],
                'search' => ['r.caller', 'r.callee', 'r.uniqueid'],
                'filterable' => ['status'],
                'sortable' => ['r.started_at', 'r.duration_seconds', 'r.created_at'],
                'default_sort' => 'r.started_at',
            ],
            'provisioning_devices' => [
                'alias' => 'd',
                'from' => 'provisioning_devices d INNER JOIN companies c ON c.id = d.company_id LEFT JOIN ps_endpoints e ON e.uuid = d.extension_uuid',
                'tenant' => true,
                'select' => ['d.uuid', 'd.company_id', 'c.name AS company_name', 'd.mac_address', 'd.vendor', 'd.model', 'd.firmware_version', 'd.display_name', 'e.extension_number', 'd.rps_enabled', 'd.status', 'd.last_provisioned_at', 'd.reboot_requested_at', 'd.created_at', 'd.updated_at'],
                'search' => ['d.mac_address', 'd.vendor', 'd.model', 'e.extension_number'],
                'filterable' => ['status'],
                'sortable' => ['d.mac_address', 'd.vendor', 'd.model', 'd.created_at', 'd.updated_at'],
                'default_sort' => 'd.created_at',
            ],
            'provisioning_templates' => [
                'alias' => 't',
                'from' => 'provisioning_templates t INNER JOIN companies c ON c.id = t.company_id',
                'tenant' => true,
                'select' => ['t.uuid', 't.company_id', 'c.name AS company_name', 't.name', 't.vendor', 't.model', 't.status', 't.created_at', 't.updated_at'],
                'search' => ['t.name', 't.vendor', 't.model'],
                'filterable' => ['status'],
                'sortable' => ['t.name', 't.vendor', 't.created_at', 't.updated_at'],
                'default_sort' => 't.name',
            ],
            'provisioning_phonebooks' => [
                'alias' => 'p',
                'from' => 'provisioning_phonebooks p INNER JOIN companies c ON c.id = p.company_id',
                'tenant' => true,
                'select' => ['p.uuid', 'p.company_id', 'c.name AS company_name', 'p.name', 'p.entries_json', 'p.status', 'p.created_at', 'p.updated_at'],
                'search' => ['p.name'],
                'filterable' => ['status'],
                'sortable' => ['p.name', 'p.created_at', 'p.updated_at'],
                'default_sort' => 'p.name',
            ],
            'crm_contacts' => [
                'alias' => 'ct',
                'from' => 'crm_contacts ct INNER JOIN companies c ON c.id = ct.company_id LEFT JOIN crm_accounts a ON a.id = ct.account_id',
                'tenant' => true,
                'select' => ['ct.uuid', 'ct.company_id', 'c.name AS company_name', 'a.uuid AS account_uuid', 'a.trade_name AS account_name', 'ct.full_name', 'ct.organization', 'ct.job_title', 'ct.email', 'ct.mobile_phone', 'ct.office_phone', 'ct.related_extension_id', 'ct.tags', 'ct.source', 'ct.external_provider', 'ct.external_id', 'ct.sync_enabled', 'ct.last_synced_at', 'ct.status', 'ct.created_at', 'ct.updated_at'],
                'search' => ['ct.full_name', 'ct.organization', 'ct.email', 'ct.mobile_phone', 'ct.office_phone', 'ct.tags'],
                'filterable' => ['status', 'source', 'external_provider'],
                'sortable' => ['ct.full_name', 'ct.email', 'ct.created_at', 'ct.updated_at'],
                'default_sort' => 'ct.full_name',
            ],
            'crm_accounts' => [
                'alias' => 'a',
                'from' => 'crm_accounts a INNER JOIN companies c ON c.id = a.company_id',
                'tenant' => true,
                'select' => ['a.uuid', 'a.company_id', 'c.name AS company_name', 'a.trade_name', 'a.legal_name', 'a.tax_id', 'a.primary_email', 'a.primary_phone', 'a.website', 'a.external_provider', 'a.external_id', 'a.sync_enabled', 'a.last_synced_at', 'a.status', 'a.created_at', 'a.updated_at'],
                'search' => ['a.trade_name', 'a.legal_name', 'a.tax_id', 'a.primary_email', 'a.primary_phone'],
                'filterable' => ['status', 'external_provider'],
                'sortable' => ['a.trade_name', 'a.created_at', 'a.updated_at'],
                'default_sort' => 'a.trade_name',
            ],
            'crm_activities' => [
                'alias' => 'ac',
                'from' => 'crm_activities ac INNER JOIN companies c ON c.id = ac.company_id LEFT JOIN crm_contacts ct ON ct.id = ac.contact_id LEFT JOIN crm_accounts a ON a.id = ac.account_id',
                'tenant' => true,
                'select' => ['ac.uuid', 'ac.company_id', 'c.name AS company_name', 'ct.uuid AS contact_uuid', 'ct.full_name AS contact_name', 'a.uuid AS account_uuid', 'a.trade_name AS account_name', 'ac.activity_type', 'ac.subject', 'ac.direction', 'ac.call_uniqueid', 'ac.occurred_at', 'ac.created_at'],
                'search' => ['ct.full_name', 'a.trade_name', 'ac.subject', 'ac.activity_type'],
                'filterable' => ['activity_type', 'direction'],
                'sortable' => ['ac.occurred_at', 'ac.created_at'],
                'default_sort' => 'ac.occurred_at',
            ],
            'crm_calls' => [
                'alias' => 'cl',
                'from' => 'crm_call_logs cl INNER JOIN companies c ON c.id = cl.company_id LEFT JOIN crm_contacts ct ON ct.id = cl.contact_id LEFT JOIN ps_endpoints e ON e.id = cl.extension_id',
                'tenant' => true,
                'select' => ['cl.uuid', 'cl.company_id', 'c.name AS company_name', 'ct.uuid AS contact_uuid', 'ct.full_name AS contact_name', 'e.extension_number', 'cl.extension_id', 'cl.direction', 'cl.src', 'cl.dst', 'cl.start_time', 'cl.answer_time', 'cl.end_time', 'cl.duration', 'cl.billsec', 'cl.disposition', 'cl.recording_path', 'cl.uniqueid', 'cl.linkedid', 'cl.created_at'],
                'search' => ['ct.full_name', 'cl.src', 'cl.dst', 'cl.uniqueid', 'cl.linkedid'],
                'filterable' => ['direction', 'disposition'],
                'sortable' => ['cl.start_time', 'cl.duration', 'cl.billsec', 'cl.created_at'],
                'default_sort' => 'cl.start_time',
            ],
            'queues' => $this->flowResource('pbx_queues', 'q', ['q.uuid', 'q.company_id', 'c.name AS company_name', 'q.name', 'q.extension', 'q.strategy', 'q.timeout_seconds', 'q.retry_seconds', 'q.wrapup_seconds', 'q.max_callers', 'q.music_on_hold', 'q.announce_position', 'q.announce_hold_time', 'q.service_level_seconds', 'q.overflow_destination_type', 'q.overflow_destination_id', 'q.failover_destination_type', 'q.failover_destination_id', 'q.recording_enabled', 'q.status', 'q.created_at', 'q.updated_at'], ['q.name', 'q.extension']),
            'queue_agents' => [
                'alias' => 'm',
                'from' => 'pbx_queue_members m INNER JOIN pbx_queues q ON q.id = m.queue_id INNER JOIN companies c ON c.id = m.company_id LEFT JOIN ps_endpoints e ON e.id = m.endpoint_id LEFT JOIN pbx_queue_agent_states s ON s.queue_id = m.queue_id AND s.endpoint_id = m.endpoint_id',
                'tenant' => true,
                'select' => ['m.uuid', 'm.company_id', 'c.name AS company_name', 'q.uuid AS queue_uuid', 'q.name AS queue_name', 'q.extension AS queue_extension', 'm.endpoint_id', 'e.extension_number', 'm.member_name', 'm.penalty', 'm.paused', 'm.pause_reason', 'COALESCE(s.state, "offline") AS realtime_state', 'm.status', 'm.last_login_at', 'm.last_logout_at', 'm.created_at', 'm.updated_at'],
                'search' => ['q.name', 'q.extension', 'm.endpoint_id', 'm.member_name', 'e.extension_number'],
                'filterable' => ['status', 'paused'],
                'sortable' => ['q.extension', 'm.endpoint_id', 'm.penalty', 'm.created_at', 'm.updated_at'],
                'default_sort' => 'q.extension',
            ],
            'queue_events' => [
                'alias' => 'ev',
                'from' => 'pbx_queue_events ev INNER JOIN companies c ON c.id = ev.company_id LEFT JOIN pbx_queues q ON q.id = ev.queue_id',
                'tenant' => true,
                'select' => ['ev.uuid', 'ev.company_id', 'c.name AS company_name', 'q.uuid AS queue_uuid', 'q.name AS queue_name', 'q.extension AS queue_extension', 'ev.endpoint_id', 'ev.call_id', 'ev.event_type', 'ev.payload_json', 'ev.occurred_at', 'ev.created_at'],
                'search' => ['q.name', 'q.extension', 'ev.endpoint_id', 'ev.call_id', 'ev.event_type'],
                'filterable' => ['event_type'],
                'sortable' => ['ev.occurred_at', 'ev.created_at'],
                'default_sort' => 'ev.occurred_at',
            ],
            'queue_metrics' => [
                'alias' => 'm',
                'from' => 'pbx_queue_metrics m INNER JOIN companies c ON c.id = m.company_id INNER JOIN pbx_queues q ON q.id = m.queue_id',
                'tenant' => true,
                'select' => ['m.uuid', 'm.company_id', 'c.name AS company_name', 'q.uuid AS queue_uuid', 'q.name AS queue_name', 'q.extension AS queue_extension', 'm.metric_date', 'm.offered_calls', 'm.answered_calls', 'm.abandoned_calls', 'm.active_calls', 'm.waiting_calls', 'm.service_level_percent', 'm.avg_hold_seconds', 'm.avg_talk_seconds', 'm.agents_online', 'm.created_at', 'm.updated_at'],
                'search' => ['q.name', 'q.extension'],
                'filterable' => [],
                'sortable' => ['m.metric_date', 'm.offered_calls', 'm.abandoned_calls', 'm.service_level_percent', 'm.created_at'],
                'default_sort' => 'm.metric_date',
            ],
            'resellers' => [
                'alias' => 'r',
                'from' => 'resellers r LEFT JOIN companies c ON c.id = r.company_id LEFT JOIN resellers pr ON pr.id = r.parent_reseller_id',
                'tenant' => true,
                'tenant_column' => 'r.company_id',
                'select' => ['r.uuid', 'r.parent_reseller_id', 'pr.name AS parent_name', 'r.company_id', 'c.name AS company_name', 'r.name', 'r.slug', 'r.custom_domain', 'r.status', 'r.created_at', 'r.updated_at'],
                'search' => ['r.name', 'r.slug', 'r.custom_domain', 'c.name'],
                'filterable' => ['status'],
                'sortable' => ['r.name', 'r.status', 'r.created_at', 'r.updated_at'],
                'default_sort' => 'r.name',
            ],
            'billing_subscriptions' => [
                'alias' => 's',
                'from' => 'billing_subscriptions s INNER JOIN companies c ON c.id = s.company_id LEFT JOIN resellers r ON r.id = s.reseller_id LEFT JOIN plans p ON p.id = s.plan_id',
                'tenant' => true,
                'select' => ['s.uuid', 's.company_id', 'c.name AS company_name', 's.reseller_id', 'r.name AS reseller_name', 'p.name AS plan_name', 's.billing_period', 's.amount', 's.currency', 's.current_period_start', 's.current_period_end', 's.grace_until', 's.auto_renew', 's.status', 's.created_at', 's.updated_at'],
                'search' => ['c.name', 'r.name', 'p.name', 's.status'],
                'filterable' => ['status'],
                'sortable' => ['s.current_period_end', 's.amount', 's.status', 's.created_at', 's.updated_at'],
                'default_sort' => 's.created_at',
            ],
            'billing_invoices' => [
                'alias' => 'i',
                'from' => 'billing_invoices i INNER JOIN companies c ON c.id = i.company_id LEFT JOIN resellers r ON r.id = i.reseller_id',
                'tenant' => true,
                'select' => ['i.uuid', 'i.invoice_number', 'i.company_id', 'c.name AS company_name', 'i.reseller_id', 'r.name AS reseller_name', 'i.status', 'i.currency', 'i.subtotal', 'i.tax_total', 'i.credit_total', 'i.total', 'i.balance_due', 'i.issue_date', 'i.due_date', 'i.paid_at', 'i.created_at'],
                'search' => ['i.invoice_number', 'c.name', 'r.name', 'i.status'],
                'filterable' => ['status'],
                'sortable' => ['i.issue_date', 'i.due_date', 'i.total', 'i.status', 'i.created_at'],
                'default_sort' => 'i.issue_date',
            ],
            'billing_usage' => [
                'alias' => 'u',
                'from' => 'billing_usage_records u INNER JOIN companies c ON c.id = u.company_id LEFT JOIN resellers r ON r.id = u.reseller_id',
                'tenant' => true,
                'select' => ['u.uuid', 'u.company_id', 'c.name AS company_name', 'u.reseller_id', 'r.name AS reseller_name', 'u.metric', 'u.quantity', 'u.unit', 'u.period_start', 'u.period_end', 'u.source', 'u.created_at'],
                'search' => ['u.metric', 'u.source', 'c.name', 'r.name'],
                'filterable' => ['metric'],
                'sortable' => ['u.period_end', 'u.quantity', 'u.created_at'],
                'default_sort' => 'u.period_end',
            ],
        ];
    }

    private function flowResource(string $table, string $alias, array $select, array $search): array
    {
        return [
            'alias' => $alias,
            'from' => $table . ' ' . $alias . ' INNER JOIN companies c ON c.id = ' . $alias . '.company_id',
            'tenant' => true,
            'select' => $select,
            'search' => $search,
            'filterable' => ['status'],
            'sortable' => [$alias . '.name', $alias . '.status', $alias . '.created_at', $alias . '.updated_at'],
            'default_sort' => $alias . '.name',
        ];
    }
}
