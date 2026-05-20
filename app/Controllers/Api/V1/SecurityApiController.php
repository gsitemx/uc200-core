<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\ApiTokenService;
use App\Services\AuditService;
use App\Services\PermissionService;
use App\Services\SecurityCenterService;
use App\Support\ApiResponse;
use RuntimeException;

final class SecurityApiController extends Controller
{
    public function fail2banStatus(Request $request): Response
    {
        $context = $this->actor($request, 'read');
        if ($context === null) {
            return ApiResponse::error('unauthenticated', 'Authentication required.', 401);
        }

        if ($this->rateLimited('security-status:' . $context['rate_key'], 60, 60)) {
            return ApiResponse::error('rate_limited', 'Too many Security Center status requests.', 429);
        }

        return ApiResponse::success((new SecurityCenterService($this->db()))->fail2banStatus());
    }

    public function bans(Request $request): Response
    {
        $context = $this->actor($request, 'bans_read');
        if ($context === null) {
            return ApiResponse::error('unauthenticated', 'Authentication required.', 401);
        }

        return ApiResponse::success((new SecurityCenterService($this->db()))->bans($context['company_id'] > 0 ? $context['company_id'] : null, 100));
    }

    public function events(Request $request): Response
    {
        $context = $this->actor($request, 'events_read');
        if ($context === null) {
            return ApiResponse::error('unauthenticated', 'Authentication required.', 401);
        }

        $service = new SecurityCenterService($this->db());
        $service->syncSecurityEvents($context['company_id'] > 0 ? $context['company_id'] : null);

        return ApiResponse::success($service->events($context['company_id'] > 0 ? $context['company_id'] : null, 100));
    }

    public function ban(Request $request): Response
    {
        return $this->ipAction($request, 'ban');
    }

    public function unban(Request $request): Response
    {
        return $this->ipAction($request, 'unban');
    }

    private function ipAction(Request $request, string $action): Response
    {
        $context = $this->actor($request, 'write');
        if ($context === null) {
            return ApiResponse::error('unauthenticated', 'Authentication required.', 401);
        }

        if ($this->rateLimited('security-action:' . $context['rate_key'], 20, 60)) {
            return ApiResponse::error('rate_limited', 'Too many Security Center write requests.', 429);
        }

        $ip = trim((string) $request->input('ip', ''));
        $reason = trim((string) $request->input('reason', 'API action'));
        if ($ip === '') {
            return ApiResponse::error('validation_error', 'ip is required.', 422);
        }

        $service = new SecurityCenterService($this->db());

        try {
            $result = $action === 'ban'
                ? $service->banIp($context['company_id'] > 0 ? $context['company_id'] : null, (int) $context['user_id'], $ip, $reason, 'api')
                : $service->unbanIp($context['company_id'] > 0 ? $context['company_id'] : null, (int) $context['user_id'], $ip, $reason, 'api');
        } catch (RuntimeException $exception) {
            return ApiResponse::error('runtime_error', $exception->getMessage(), 422);
        }

        (new AuditService($this->db()))->record('api.security.' . $action, 'security_bans', null, [
            'ip' => $ip,
            'reason' => $reason,
            'ok' => $result['ok'] ?? false,
        ], $context['company_id'] > 0 ? $context['company_id'] : null);

        return ($result['ok'] ?? false)
            ? ApiResponse::success($result)
            : ApiResponse::error('security_action_failed', (string) ($result['message'] ?? 'Security action failed.'), 422, $result);
    }

    private function actor(Request $request, string $mode): ?array
    {
        $tokenService = new ApiTokenService($this->db());
        $token = $tokenService->authenticate($request);
        if ($token !== null) {
            $requiredScope = match ($mode) {
                'read' => 'security_fail2ban:read',
                'bans_read' => 'security_bans:read',
                'events_read' => 'security_events:read',
                default => 'security_bans:write',
            };
            if (! $tokenService->can($token, $requiredScope)) {
                return null;
            }

            return [
                'user_id' => (int) $token['user_id'],
                'company_id' => $token['company_id'] !== null ? (int) $token['company_id'] : 0,
                'rate_key' => 'token:' . (string) $token['id'],
            ];
        }

        $userId = (int) Session::get('user_id', 0);
        if ($userId <= 0) {
            return null;
        }

        $permission = in_array($mode, ['read', 'bans_read', 'events_read'], true) ? 'security.view' : 'security.manage';
        if (! $this->hasPermission($userId, $permission)) {
            return null;
        }

        return [
            'user_id' => $userId,
            'company_id' => (int) Session::get('company_id', 0),
            'rate_key' => 'session:' . $userId,
        ];
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
