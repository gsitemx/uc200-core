<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Core\Session;
use PDO;

final class AuditService
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function record(string $action, ?string $entityType = null, ?int $entityId = null, array $metadata = [], ?int $companyId = null): void
    {
        $request = new Request();
        $statement = $this->db->prepare(
            'INSERT INTO audit_logs (uuid, company_id, user_id, action, entity_type, entity_id, ip_address, user_agent, metadata)
             VALUES (:uuid, :company_id, :user_id, :action, :entity_type, :entity_id, :ip_address, :user_agent, :metadata)'
        );

        $statement->execute([
            'uuid' => uuid(),
            'company_id' => $companyId ?? Session::get('company_id'),
            'user_id' => Session::get('user_id'),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR),
        ]);
    }
}
