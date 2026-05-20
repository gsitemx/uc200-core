<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\ApiTokenService;
use App\Services\AuditService;
use App\Services\CallLogService;
use App\Support\ApiResponse;
use PDO;

final class CrmApiController extends Controller
{
    private array $sources = ['manual', 'microsoft', 'google', 'whatsapp', 'api'];

    public function lookupContact(Request $request): Response
    {
        $token = $this->requireToken($request, 'crm_contacts:read');
        if ($token instanceof Response) return $token;

        $companyId = $token['company_id'] !== null ? (int) $token['company_id'] : (int) $request->input('company_id', 0);
        $number = trim((string) $request->input('number', ''));

        if ($companyId <= 0 || $number === '') {
            return ApiResponse::error('validation_error', 'company_id and number are required.', 422);
        }

        $contact = (new CallLogService($this->db()))->callerLookup($companyId, $number);
        if ($contact === null) {
            return ApiResponse::error('not_found', 'Contact not found.', 404);
        }

        return ApiResponse::success($contact);
    }

    public function storeContact(Request $request): Response
    {
        $token = $this->requireToken($request, 'crm_contacts:write');
        if ($token instanceof Response) return $token;
        $data = $this->contactPayload($request, $token);
        if ($data['full_name'] === '') return ApiResponse::error('validation_error', 'full_name is required.', 422);

        $this->db()->prepare(
            'INSERT INTO crm_contacts
             (uuid, company_id, account_id, related_extension_id, related_did, full_name, organization, job_title, email, mobile_phone, office_phone, tags, notes, source, external_provider, external_id, sync_enabled, status)
             VALUES (:uuid, :company_id, :account_id, :related_extension_id, :related_did, :full_name, :organization, :job_title, :email, :mobile_phone, :office_phone, :tags, :notes, :source, :external_provider, :external_id, :sync_enabled, :status)'
        )->execute(['uuid' => uuid()] + $data);
        (new AuditService($this->db()))->record('api.crm.contact.created', 'crm_contacts', null, ['name' => $data['full_name']], (int) $data['company_id']);

        return ApiResponse::success(['created' => true], [], 201);
    }

    public function updateContact(Request $request): Response
    {
        $token = $this->requireToken($request, 'crm_contacts:write');
        if ($token instanceof Response) return $token;
        $contact = $this->ownedRow('crm_contacts', (string) $request->input('id'), $token);
        if ($contact === null) return ApiResponse::error('not_found', 'Contact not found.', 404);
        $data = $this->contactPayload($request, $token, (int) $contact['company_id']);
        if ($data['full_name'] === '') return ApiResponse::error('validation_error', 'full_name is required.', 422);
        unset($data['company_id']);

        $this->db()->prepare(
            'UPDATE crm_contacts
             SET account_id = :account_id, related_extension_id = :related_extension_id, related_did = :related_did, full_name = :full_name,
                 organization = :organization, job_title = :job_title, email = :email, mobile_phone = :mobile_phone,
                 office_phone = :office_phone, tags = :tags, notes = :notes, source = :source,
                 external_provider = :external_provider, external_id = :external_id, sync_enabled = :sync_enabled, status = :status
             WHERE id = :row_id'
        )->execute($data + ['row_id' => (int) $contact['id']]);
        (new AuditService($this->db()))->record('api.crm.contact.updated', 'crm_contacts', (int) $contact['id'], ['name' => $data['full_name']], (int) $contact['company_id']);

        return ApiResponse::success(['updated' => true]);
    }

    public function deleteContact(Request $request): Response
    {
        return $this->softDelete($request, 'crm_contacts', 'crm_contacts:write');
    }

    public function storeAccount(Request $request): Response
    {
        $token = $this->requireToken($request, 'crm_accounts:write');
        if ($token instanceof Response) return $token;
        $data = $this->accountPayload($request, $token);
        if ($data['trade_name'] === '') return ApiResponse::error('validation_error', 'trade_name is required.', 422);

        $this->db()->prepare(
            'INSERT INTO crm_accounts
             (uuid, company_id, trade_name, legal_name, tax_id, primary_email, primary_phone, website, address, notes, external_provider, external_id, sync_enabled, status)
             VALUES (:uuid, :company_id, :trade_name, :legal_name, :tax_id, :primary_email, :primary_phone, :website, :address, :notes, :external_provider, :external_id, :sync_enabled, :status)'
        )->execute(['uuid' => uuid()] + $data);
        (new AuditService($this->db()))->record('api.crm.account.created', 'crm_accounts', null, ['name' => $data['trade_name']], (int) $data['company_id']);

        return ApiResponse::success(['created' => true], [], 201);
    }

    public function updateAccount(Request $request): Response
    {
        $token = $this->requireToken($request, 'crm_accounts:write');
        if ($token instanceof Response) return $token;
        $account = $this->ownedRow('crm_accounts', (string) $request->input('id'), $token);
        if ($account === null) return ApiResponse::error('not_found', 'Account not found.', 404);
        $data = $this->accountPayload($request, $token, (int) $account['company_id']);
        if ($data['trade_name'] === '') return ApiResponse::error('validation_error', 'trade_name is required.', 422);
        unset($data['company_id']);

        $this->db()->prepare(
            'UPDATE crm_accounts
             SET trade_name = :trade_name, legal_name = :legal_name, tax_id = :tax_id, primary_email = :primary_email,
                 primary_phone = :primary_phone, website = :website, address = :address, notes = :notes,
                 external_provider = :external_provider, external_id = :external_id, sync_enabled = :sync_enabled, status = :status
             WHERE id = :row_id'
        )->execute($data + ['row_id' => (int) $account['id']]);
        (new AuditService($this->db()))->record('api.crm.account.updated', 'crm_accounts', (int) $account['id'], ['name' => $data['trade_name']], (int) $account['company_id']);

        return ApiResponse::success(['updated' => true]);
    }

    public function deleteAccount(Request $request): Response
    {
        return $this->softDelete($request, 'crm_accounts', 'crm_accounts:write');
    }

    private function requireToken(Request $request, string $scope): array|Response
    {
        $service = new ApiTokenService($this->db());
        $token = $service->authenticate($request);
        if ($token === null) return ApiResponse::error('unauthenticated', 'Bearer token required.', 401);
        if (! $service->can($token, $scope)) return ApiResponse::error('forbidden', 'Missing required scope: ' . $scope, 403);

        return $token;
    }

    private function contactPayload(Request $request, array $token, ?int $companyId = null): array
    {
        return [
            'company_id' => $companyId ?? $this->companyId($request, $token),
            'account_id' => (int) $request->input('account_id', 0) > 0 ? (int) $request->input('account_id') : null,
            'related_extension_id' => $this->nullable($request->input('related_extension_id')),
            'related_did' => $this->nullable($request->input('related_did')),
            'full_name' => trim((string) $request->input('full_name')),
            'organization' => $this->nullable($request->input('organization')),
            'job_title' => $this->nullable($request->input('job_title')),
            'email' => $this->nullable(strtolower(trim((string) $request->input('email')))),
            'mobile_phone' => $this->nullable($request->input('mobile_phone')),
            'office_phone' => $this->nullable($request->input('office_phone')),
            'tags' => $this->nullable($request->input('tags')),
            'notes' => $this->nullable($request->input('notes')),
            'source' => $this->source((string) $request->input('source', 'api')),
            'external_provider' => $this->source((string) $request->input('external_provider', (string) $request->input('source', 'api'))),
            'external_id' => $this->nullable($request->input('external_id')),
            'sync_enabled' => (int) $request->input('sync_enabled', 0) === 1 ? 1 : 0,
            'status' => in_array((string) $request->input('status', 'active'), ['active', 'inactive'], true) ? (string) $request->input('status', 'active') : 'active',
        ];
    }

    private function accountPayload(Request $request, array $token, ?int $companyId = null): array
    {
        return [
            'company_id' => $companyId ?? $this->companyId($request, $token),
            'trade_name' => trim((string) $request->input('trade_name')),
            'legal_name' => $this->nullable($request->input('legal_name')),
            'tax_id' => $this->nullable($request->input('tax_id')),
            'primary_email' => $this->nullable(strtolower(trim((string) $request->input('primary_email')))),
            'primary_phone' => $this->nullable($request->input('primary_phone')),
            'website' => $this->nullable($request->input('website')),
            'address' => $this->nullable($request->input('address')),
            'notes' => $this->nullable($request->input('notes')),
            'external_provider' => $this->source((string) $request->input('external_provider', 'api')),
            'external_id' => $this->nullable($request->input('external_id')),
            'sync_enabled' => (int) $request->input('sync_enabled', 0) === 1 ? 1 : 0,
            'status' => in_array((string) $request->input('status', 'active'), ['active', 'inactive'], true) ? (string) $request->input('status', 'active') : 'active',
        ];
    }

    private function companyId(Request $request, array $token): int
    {
        return $token['company_id'] !== null ? (int) $token['company_id'] : (int) $request->input('company_id', 0);
    }

    private function ownedRow(string $table, string $uuid, array $token): ?array
    {
        $sql = 'SELECT * FROM ' . $table . ' WHERE uuid = :uuid AND deleted_at IS NULL';
        $params = ['uuid' => $uuid];
        if ($token['company_id'] !== null) {
            $sql .= ' AND company_id = :company_id';
            $params['company_id'] = (int) $token['company_id'];
        }
        $statement = $this->db()->prepare($sql . ' LIMIT 1');
        $statement->execute($params);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function softDelete(Request $request, string $table, string $scope): Response
    {
        $token = $this->requireToken($request, $scope);
        if ($token instanceof Response) return $token;
        $row = $this->ownedRow($table, (string) $request->input('id'), $token);
        if ($row === null) return ApiResponse::error('not_found', 'Resource not found.', 404);
        $this->db()->prepare('UPDATE ' . $table . ' SET deleted_at = NOW(), status = "inactive" WHERE id = :id')->execute(['id' => (int) $row['id']]);
        (new AuditService($this->db()))->record('api.crm.resource.deleted', $table, (int) $row['id'], ['uuid' => $row['uuid'] ?? null], (int) $row['company_id']);

        return ApiResponse::success(['deleted' => true]);
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function source(string $source): string
    {
        return in_array($source, $this->sources, true) ? $source : 'api';
    }
}
