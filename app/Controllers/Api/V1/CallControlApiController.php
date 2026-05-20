<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\ApiTokenService;
use App\Services\AuditService;
use App\Services\CallControlService;
use App\Services\PermissionService;
use App\Support\ApiResponse;

final class CallControlApiController extends Controller
{
    public function hold(Request $request): Response
    {
        return $this->perform($request, 'call.hold', 'calls_control:write', 'call.hold', fn (array $context) => (new CallControlService($this->db()))
            ->hold($context['company_id'], (string) $request->input('origin_extension', ''), $this->payload($request, $context)));
    }

    public function unhold(Request $request): Response
    {
        return $this->perform($request, 'call.hold', 'calls_control:write', 'call.unhold', fn (array $context) => (new CallControlService($this->db()))
            ->unhold($context['company_id'], (string) $request->input('origin_extension', ''), $this->payload($request, $context)));
    }

    public function transfer(Request $request): Response
    {
        return $this->perform($request, 'call.transfer', 'calls_control:write', 'call.transfer', fn (array $context) => (new CallControlService($this->db()))
            ->transfer(
                $context['company_id'],
                (string) $request->input('origin_extension', ''),
                (string) $request->input('channel', ''),
                (string) $request->input('destination', ''),
                $this->payload($request, $context) + ['mode' => (string) $request->input('mode', 'blind')]
            ));
    }

    public function hangup(Request $request): Response
    {
        return $this->perform($request, 'pbx.originate', 'calls_control:write', 'call.hangup', fn (array $context) => (new CallControlService($this->db()))
            ->hangup(
                $context['company_id'],
                (string) $request->input('origin_extension', ''),
                (string) $request->input('channel', ''),
                $this->payload($request, $context)
            ));
    }

    public function park(Request $request): Response
    {
        return $this->perform($request, 'call.park', 'calls_control:write', 'call.park', fn (array $context) => (new CallControlService($this->db()))
            ->park(
                $context['company_id'],
                (string) $request->input('origin_extension', ''),
                (string) $request->input('channel', ''),
                $this->payload($request, $context)
            ));
    }

    public function pickup(Request $request): Response
    {
        return $this->perform($request, 'call.pickup', 'calls_control:write', 'call.pickup', fn (array $context) => (new CallControlService($this->db()))
            ->pickup(
                $context['company_id'],
                (string) $request->input('origin_extension', ''),
                (string) $request->input('target_extension', ''),
                $this->payload($request, $context)
            ));
    }

    private function perform(Request $request, string $permission, string $scope, string $auditAction, callable $callback): Response
    {
        $context = $this->actor($request, $permission, $scope);
        if ($context === null) {
            return ApiResponse::error('unauthenticated', 'Authentication or permission required.', 401);
        }

        if ($context['company_id'] <= 0) {
            return ApiResponse::error('tenant_required', 'A tenant context is required.', 422);
        }

        if ($this->rateLimited('call-control:' . $auditAction . ':' . $context['rate_key'], 40, 60)) {
            return ApiResponse::error('rate_limited', 'Too many call control requests.', 429);
        }

        $result = $callback($context);
        (new AuditService($this->db()))->record($auditAction, 'ps_endpoints', null, [
            'origin_extension' => (string) $request->input('origin_extension', ''),
            'destination' => (string) $request->input('destination', ''),
            'target_extension' => (string) $request->input('target_extension', ''),
            'channel' => (string) $request->input('channel', ''),
            'mode' => (string) ($result['mode'] ?? ''),
            'success' => (bool) ($result['success'] ?? false),
        ], $context['company_id']);

        return ($result['success'] ?? false)
            ? ApiResponse::success($result)
            : ApiResponse::error('call_control_failed', (string) ($result['message'] ?? 'Call control action failed.'), 422, $result);
    }

    private function payload(Request $request, array $context): array
    {
        return [
            'requested_by_user_id' => (int) $context['user_id'],
            'source' => $context['source'],
            'call_id' => (string) $request->input('call_id', ''),
            'linkedid' => (string) $request->input('linkedid', ''),
            'timeout_ms' => (int) $request->input('timeout_ms', 45000),
            'parking_lot' => (string) $request->input('parking_lot', ''),
            'pickup_code' => (string) $request->input('pickup_code', '*8'),
            'dial_context' => (string) $request->input('dial_context', ''),
        ];
    }

    private function actor(Request $request, string $permission, string $scope): ?array
    {
        $tokenService = new ApiTokenService($this->db());
        $token = $tokenService->authenticate($request);
        if ($token !== null) {
            if (! $tokenService->can($token, $scope)) {
                return null;
            }

            return [
                'user_id' => (int) $token['user_id'],
                'company_id' => $token['company_id'] !== null ? (int) $token['company_id'] : (int) $request->input('company_id', 0),
                'rate_key' => 'token:' . (string) $token['id'],
                'source' => 'api',
            ];
        }

        $userId = (int) Session::get('user_id', 0);
        if ($userId <= 0) {
            return null;
        }

        if (! $this->hasPermission($userId, $permission)) {
            return null;
        }

        return [
            'user_id' => $userId,
            'company_id' => has_role('super-admin') ? (int) $request->input('company_id', Session::get('company_id', 0)) : (int) Session::get('company_id', 0),
            'rate_key' => 'session:' . $userId,
            'source' => 'panel',
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
