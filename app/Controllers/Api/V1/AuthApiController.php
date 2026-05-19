<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\ApiTokenService;
use App\Support\ApiResponse;
use App\Support\Validator;

final class AuthApiController extends Controller
{
    public function token(Request $request): Response
    {
        $tokenService = new ApiTokenService($this->db());

        if ($tokenService->hitRateLimit('auth:' . (string) $request->ip(), 20, 60)) {
            return ApiResponse::error('rate_limited', 'Too many authentication attempts.', 429);
        }

        $data = [
            'email' => strtolower(trim((string) $request->input('email'))),
            'password' => (string) $request->input('password'),
            'name' => trim((string) $request->input('name', 'API Token')),
        ];
        $errors = Validator::validate($data, ['email' => 'required|email', 'password' => 'required', 'name' => 'required|max:120']);

        if ($errors !== []) {
            return ApiResponse::error('validation_failed', 'Invalid request payload.', 422, $errors);
        }

        $statement = $this->db()->prepare(
            'SELECT u.*, c.status AS company_status
             FROM users u
             LEFT JOIN companies c ON c.id = u.company_id
             WHERE u.email = :email AND u.deleted_at IS NULL LIMIT 1'
        );
        $statement->execute(['email' => $data['email']]);
        $user = $statement->fetch();

        if ($user === false || (int) $user['is_active'] !== 1 || ! password_verify($data['password'], (string) $user['password_hash'])) {
            return ApiResponse::error('invalid_credentials', 'Invalid credentials.', 401);
        }

        $scopes = $this->scopes($request->input('scopes', ['*']));
        $expiresAt = $this->expiresAt((string) $request->input('expires_at', ''), (int) $request->input('expires_in_days', 30));
        $created = $tokenService->create((int) $user['id'], $user['company_id'] !== null ? (int) $user['company_id'] : null, $data['name'], $scopes, $expiresAt, (string) $request->input('allowed_ips', ''));

        return ApiResponse::success([
            'token_type' => 'Bearer',
            'access_token' => $created['token'],
            'expires_at' => $expiresAt,
            'scopes' => $scopes,
        ], [], 201);
    }

    public function me(Request $request): Response
    {
        $token = $this->requireToken($request, 'auth:read');
        if ($token instanceof Response) {
            return $token;
        }

        return ApiResponse::success([
            'user' => [
                'id' => (int) $token['user_id'],
                'name' => $token['user_name'],
                'email' => $token['user_email'],
            ],
            'company' => [
                'id' => $token['company_id'] !== null ? (int) $token['company_id'] : null,
                'uuid' => $token['company_uuid'],
                'name' => $token['company_name'],
            ],
            'scopes' => $token['scopes_array'],
        ]);
    }

    public function revoke(Request $request): Response
    {
        $token = $this->requireToken($request, 'auth:write');
        if ($token instanceof Response) {
            return $token;
        }

        $uuid = (string) $request->input('id', $token['uuid']);
        $revoked = (new ApiTokenService($this->db()))->revoke($uuid, (int) $token['user_id']);

        return $revoked
            ? ApiResponse::success(['revoked' => true])
            : ApiResponse::error('not_found', 'Token not found.', 404);
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

    private function scopes(mixed $input): array
    {
        if (is_array($input)) {
            return array_values(array_filter(array_map('strval', $input)));
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $input)))) ?: ['*'];
    }

    private function expiresAt(string $expiresAt, int $days): ?string
    {
        if ($expiresAt !== '') {
            return str_replace('T', ' ', substr($expiresAt, 0, 19));
        }

        if ($days <= 0) {
            return null;
        }

        return gmdate('Y-m-d H:i:s', time() + min($days, 365) * 86400);
    }
}
