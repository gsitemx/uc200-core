<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AmiService;
use App\Services\ApiTokenService;
use App\Services\AuditService;
use App\Services\PermissionService;
use App\Services\PbxOriginateService;
use App\Support\ApiResponse;

final class PbxApiController extends Controller
{
    public function amiStatus(Request $request): Response
    {
        $context = $this->actor($request, 'status');
        if ($context === null) {
            return ApiResponse::error('unauthenticated', 'Authentication required.', 401);
        }

        if ($this->rateLimited('ami-status:' . $context['rate_key'], 60, 60)) {
            return ApiResponse::error('rate_limited', 'Too many AMI status requests.', 429);
        }

        $companyId = $context['company_id'] > 0 ? $context['company_id'] : null;
        $status = (new AmiService($this->db()))->status($companyId);

        return ApiResponse::success($status);
    }

    public function originate(Request $request): Response
    {
        $context = $this->actor($request, 'originate');
        if ($context === null) {
            return ApiResponse::error('unauthenticated', 'Authentication required.', 401);
        }

        if ($this->rateLimited('originate:' . $context['rate_key'], 20, 60)) {
            return ApiResponse::error('rate_limited', 'Too many originate requests.', 429);
        }

        $companyId = $context['company_id'] > 0 ? $context['company_id'] : (int) $request->input('company_id', 0);
        $origin = trim((string) $request->input('origin_extension', ''));
        $destination = trim((string) $request->input('destination', ''));

        if ($companyId <= 0 || $origin === '' || $destination === '') {
            return ApiResponse::error('validation_error', 'company_id, origin_extension and destination are required.', 422);
        }

        $result = (new PbxOriginateService($this->db()))->request($companyId, $origin, $destination, [
            'requested_by_user_id' => (int) $context['user_id'],
            'source' => $context['source'],
        ]);

        (new AuditService($this->db()))->record('api.pbx.originate.requested', 'ps_endpoints', null, [
            'origin_extension' => $origin,
            'destination' => $destination,
            'simulated' => $result['simulated'] ?? false,
            'queued' => $result['queued'] ?? false,
            'source' => $context['source'],
        ], $companyId);

        return ($result['queued'] ?? false)
            ? ApiResponse::success($result)
            : ApiResponse::error('originate_failed', (string) ($result['message'] ?? 'Originate failed.'), 422, $result);
    }

    private function actor(Request $request, string $mode): ?array
    {
        $tokenService = new ApiTokenService($this->db());
        $token = $tokenService->authenticate($request);
        if ($token !== null) {
            $requiredScope = $mode === 'status' ? 'pbx_ami:read' : 'pbx_originate:write';
            if (! $tokenService->can($token, $requiredScope)) {
                return null;
            }

            return [
                'user_id' => (int) $token['user_id'],
                'company_id' => $token['company_id'] !== null ? (int) $token['company_id'] : 0,
                'rate_key' => 'token:' . (string) $token['id'],
                'source' => 'api',
            ];
        }

        $userId = (int) Session::get('user_id', 0);
        if ($userId <= 0) {
            return null;
        }

        if (! $this->hasPermission($userId, $mode === 'status' ? 'pbx.view' : 'pbx.originate')) {
            return null;
        }

        return [
            'user_id' => $userId,
            'company_id' => (int) Session::get('company_id', 0),
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
