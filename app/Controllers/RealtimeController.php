<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\ApiTokenService;
use App\Services\AuditService;
use App\Services\PermissionService;
use App\Services\RealtimeConfigService;
use App\Services\RealtimeTokenService;
use App\Support\ApiResponse;

final class RealtimeController extends Controller
{
    public function token(Request $request): Response
    {
        if ((int) Session::get('user_id', 0) <= 0) {
            return ApiResponse::error('unauthenticated', 'Session required.', 401);
        }

        $userId = (int) Session::get('user_id', 0);
        if (! $this->hasPermission($userId, 'pbx.view')) {
            return ApiResponse::error('forbidden', 'No tienes permiso para usar realtime PBX.', 403);
        }

        if ($this->rateLimited('realtime:token:' . $userId, 60, 60)) {
            return ApiResponse::error('rate_limited', 'Too many realtime token requests.', 429);
        }

        $companyId = (int) Session::get('company_id', 0);
        if ($companyId <= 0) {
            return ApiResponse::error('tenant_required', 'Tenant context required.', 422);
        }

        $configService = new RealtimeConfigService($this->db());
        $settings = $configService->settings($companyId);
        if (! ($settings['enabled'] ?? false)) {
            return ApiResponse::error('realtime_disabled', 'Realtime is disabled for this tenant.', 423);
        }

        $extension = $this->userExtension($companyId);
        $issued = (new RealtimeTokenService($this->db(), $configService))->issue([
            'sub' => (string) $userId,
            'user_id' => $userId,
            'company_id' => $companyId,
            'company_name' => (string) Session::get('company_name', ''),
            'user_name' => (string) Session::get('user_name', ''),
            'user_email' => (string) Session::get('user_email', ''),
            'roles' => array_values(array_map('strval', Session::get('user_roles', []))),
            'endpoint_id' => $extension['id'] ?? null,
            'extension_number' => $extension['extension_number'] ?? null,
        ], (int) ($settings['jwt_ttl_seconds'] ?? 900));

        (new AuditService($this->db()))->record('realtime.token.issued', 'settings', null, [
            'socket_path' => $settings['socket_path'],
            'public_url' => $settings['public_url'],
            'endpoint_id' => $extension['id'] ?? null,
        ], $companyId);

        return ApiResponse::success([
            'token' => $issued['token'],
            'expires_at' => $issued['expires_at'],
            'expires_in' => $issued['expires_in'],
            'socket' => [
                'url' => (string) ($settings['public_url'] ?? ''),
                'path' => (string) ($settings['socket_path'] ?? '/socket.io'),
                'script_url' => (string) ($settings['socket_script'] ?? '/socket.io/socket.io.js'),
                'transports' => ['websocket'],
            ],
            'tenant' => [
                'company_id' => $companyId,
                'company_name' => (string) Session::get('company_name', ''),
            ],
            'user' => [
                'id' => $userId,
                'name' => (string) Session::get('user_name', ''),
                'email' => (string) Session::get('user_email', ''),
            ],
            'extension' => $extension,
        ]);
    }

    public function status(Request $request): Response
    {
        if ((int) Session::get('user_id', 0) <= 0) {
            return ApiResponse::error('unauthenticated', 'Session required.', 401);
        }

        $userId = (int) Session::get('user_id', 0);
        if (! $this->hasPermission($userId, 'pbx.view')) {
            return ApiResponse::error('forbidden', 'No tienes permiso para ver el estado realtime.', 403);
        }

        $companyId = (int) Session::get('company_id', 0);
        $configService = new RealtimeConfigService($this->db());
        $settings = $configService->settings($companyId > 0 ? $companyId : null);
        $health = $configService->health($companyId > 0 ? $companyId : null);

        return ApiResponse::success([
            'enabled' => (bool) ($settings['enabled'] ?? false),
            'socket' => [
                'url' => (string) ($settings['public_url'] ?? ''),
                'path' => (string) ($settings['socket_path'] ?? '/socket.io'),
                'script_url' => (string) ($settings['socket_script'] ?? '/socket.io/socket.io.js'),
            ],
            'health' => $health,
        ]);
    }

    private function userExtension(int $companyId): ?array
    {
        if ($companyId <= 0) {
            return null;
        }

        $statement = $this->db()->prepare(
            'SELECT e.id, e.uuid, e.extension_number, e.context, e.presence_status, e.sip_status
             FROM ps_endpoints e
             INNER JOIN users u ON u.company_id = e.company_id
             WHERE u.id = :user_id
               AND e.company_id = :company_id
               AND e.deleted_at IS NULL
               AND (u.email = e.email OR u.email = e.contact_email)
             LIMIT 1'
        );
        $statement->execute([
            'user_id' => (int) Session::get('user_id', 0),
            'company_id' => $companyId,
        ]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    private function hasPermission(int $userId, string $permission): bool
    {
        return has_role('super-admin') || (new PermissionService($this->db()))->userHasPermission($userId, $permission);
    }

    private function rateLimited(string $key, int $limit, int $windowSeconds): bool
    {
        return (new ApiTokenService($this->db()))->hitRateLimit($key, $limit, $windowSeconds);
    }
}
