<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\ApiTokenService;
use App\Services\AuditService;

final class ApiTokenController extends Controller
{
    public function index(Request $request): string
    {
        $service = new ApiTokenService($this->db());

        return view('api/tokens/index', [
            'title' => 'API Tokens',
            'tokens' => $service->tokensForUser((int) Session::get('user_id'), has_role('super-admin')),
            'flash' => Session::flash('success'),
            'plainToken' => Session::flash('plain_api_token'),
            'scopes' => $this->availableScopes(),
        ]);
    }

    public function store(Request $request): void
    {
        $name = trim((string) $request->input('name', 'API Token'));
        $scopes = array_values(array_filter(array_map('strval', (array) $request->input('scopes', [])))) ?: ['*'];
        $expiresAt = trim((string) $request->input('expires_at', ''));
        $allowedIps = trim((string) $request->input('allowed_ips', ''));

        $created = (new ApiTokenService($this->db()))->create(
            (int) Session::get('user_id'),
            Session::get('company_id') !== null ? (int) Session::get('company_id') : null,
            $name !== '' ? $name : 'API Token',
            $scopes,
            $expiresAt !== '' ? str_replace('T', ' ', substr($expiresAt, 0, 19)) : null,
            $allowedIps
        );

        (new AuditService($this->db()))->record('api.token.created', 'api_tokens', null, [
            'uuid' => $created['uuid'],
            'name' => $name,
            'scopes' => $scopes,
        ], Session::get('company_id') !== null ? (int) Session::get('company_id') : null);

        Session::flash('plain_api_token', $created['token']);
        Session::flash('success', 'Token creado. Copia el valor ahora; no se volvera a mostrar.');
        redirect('/settings/api-tokens');
    }

    public function revoke(Request $request): void
    {
        $uuid = (string) $request->input('id', '');
        $revoked = (new ApiTokenService($this->db()))->revoke($uuid, (int) Session::get('user_id'), has_role('super-admin'));

        if ($revoked) {
            (new AuditService($this->db()))->record('api.token.revoked', 'api_tokens', null, ['uuid' => $uuid], Session::get('company_id') !== null ? (int) Session::get('company_id') : null);
            Session::flash('success', 'Token revocado correctamente.');
        }

        redirect('/settings/api-tokens');
    }

    private function availableScopes(): array
    {
        return [
            '*' => 'Acceso completo',
            'auth:read' => 'Auth lectura',
            'auth:write' => 'Auth escritura',
            'companies:read' => 'Empresas lectura',
            'users:read' => 'Usuarios lectura',
            'extensions:read' => 'Extensiones lectura',
            'ringgroups:read' => 'Ring Groups lectura',
            'ivr:read' => 'IVR lectura',
            'trunks:read' => 'Trunks lectura',
            'recordings:read' => 'Grabaciones lectura',
            'provisioning_devices:read' => 'Provisioning devices lectura',
            'provisioning_templates:read' => 'Provisioning templates lectura',
            'provisioning_phonebooks:read' => 'Provisioning phonebooks lectura',
            'queues:read' => 'Queues lectura',
            'queue_agents:read' => 'Queue agents lectura',
            'queue_events:read' => 'Queue events lectura',
            'queue_metrics:read' => 'Queue metrics lectura',
            'resellers:read' => 'Resellers lectura',
            'billing_subscriptions:read' => 'Billing subscriptions lectura',
            'billing_invoices:read' => 'Billing invoices lectura',
            'billing_usage:read' => 'Billing usage lectura',
        ];
    }
}
