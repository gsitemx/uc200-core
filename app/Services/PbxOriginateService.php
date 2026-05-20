<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class PbxOriginateService
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function request(int $companyId, string $originExtensionId, string $destination, array $context = []): array
    {
        $endpoint = $this->originEndpoint($companyId, $originExtensionId);
        if ($endpoint === null) {
            return [
                'queued' => false,
                'simulated' => true,
                'supported' => false,
                'message' => 'La extension origen no pertenece al tenant o no existe.',
            ];
        }

        $destination = trim($destination);
        if ($destination === '') {
            return [
                'queued' => false,
                'simulated' => false,
                'supported' => false,
                'message' => 'Destino vacio.',
            ];
        }

        $ami = new AmiService($this->db);
        $amiStatus = $ami->status($companyId);
        if (($amiStatus['connected'] ?? false) !== true) {
            return [
                'queued' => false,
                'simulated' => false,
                'supported' => false,
                'message' => (string) ($amiStatus['message'] ?? 'AMI no disponible.'),
                'origin_extension_id' => $endpoint['id'],
                'origin_extension_number' => $endpoint['extension_number'],
                'destination' => $destination,
            ];
        }

        $dialContext = trim((string) ($context['dial_context'] ?? $context['context'] ?? $endpoint['context'] ?? ''));
        if ($dialContext === '') {
            $dialContext = 'contexto_principal';
        }
        $result = $ami->originate($companyId, (string) $endpoint['id'], $destination, [
            'context' => $dialContext,
            'callerid' => (string) ($endpoint['callerid'] ?: $endpoint['extension_number']),
            'timeout_ms' => (int) ($context['timeout_ms'] ?? 30000),
            'variables' => [
                'UC200_SOURCE' => (string) ($context['source'] ?? 'panel'),
                'UC200_REQUESTED_BY_USER_ID' => (string) ($context['requested_by_user_id'] ?? ''),
                'UC200_CONTACT_UUID' => (string) ($context['contact_uuid'] ?? ''),
            ],
        ]);

        return $result + [
            'origin_extension_id' => $endpoint['id'],
            'origin_extension_number' => $endpoint['extension_number'],
            'destination' => $destination,
            'dial_context' => $dialContext,
        ];
    }

    private function originEndpoint(int $companyId, string $originExtensionId): ?array
    {
        $statement = $this->db->prepare(
            'SELECT id, extension_number, context, callerid
             FROM ps_endpoints
             WHERE company_id = :company_id AND deleted_at IS NULL AND (id = :value OR extension_number = :value)
             LIMIT 1'
        );
        $statement->execute(['company_id' => $companyId, 'value' => $originExtensionId]);
        $endpoint = $statement->fetch();

        return is_array($endpoint) ? $endpoint : null;
    }
}
