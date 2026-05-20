<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class CallControlService
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function hold(int $companyId, string $originExtensionId, array $payload = []): array
    {
        return $this->clientSideResult('hold', $companyId, $originExtensionId, $payload, 'Hold aplicado en el cliente UC200.');
    }

    public function unhold(int $companyId, string $originExtensionId, array $payload = []): array
    {
        return $this->clientSideResult('unhold', $companyId, $originExtensionId, $payload, 'Llamada reanudada en el cliente UC200.');
    }

    public function hangup(int $companyId, string $originExtensionId, string $channel, array $payload = []): array
    {
        $endpoint = $this->originEndpoint($companyId, $originExtensionId);
        if ($endpoint === null) {
            return $this->error('La identidad origen no pertenece al tenant o no existe.');
        }

        if (trim($channel) === '') {
            return $this->error('Se requiere el canal activo para colgar desde el motor de comunicaciones.');
        }

        $result = (new AmiService($this->db))->hangup($companyId, $channel);

        return [
            'success' => (bool) ($result['success'] ?? false),
            'mode' => 'communications-engine',
            'action' => 'hangup',
            'message' => (string) ($result['message'] ?? 'Colgado solicitado.'),
            'endpoint_id' => (string) $endpoint['id'],
            'channel' => $channel,
        ] + $payload;
    }

    public function transfer(int $companyId, string $originExtensionId, string $channel, string $destination, array $payload = []): array
    {
        $endpoint = $this->originEndpoint($companyId, $originExtensionId);
        if ($endpoint === null) {
            return $this->error('La identidad origen no pertenece al tenant o no existe.');
        }

        $destination = trim($destination);
        if ($destination === '') {
            return $this->error('Destino vacio.');
        }

        if (trim($channel) === '') {
            return $this->error('Se requiere el canal activo para transferir desde el motor de comunicaciones.');
        }

        $dialContext = trim((string) ($payload['dial_context'] ?? $endpoint['context'] ?? '')) ?: 'contexto_principal';
        $mode = in_array(($payload['mode'] ?? 'blind'), ['blind', 'attended'], true) ? (string) $payload['mode'] : 'blind';
        $result = (new AmiService($this->db))->redirect($companyId, $channel, $dialContext, $destination, 1);

        return [
            'success' => (bool) ($result['success'] ?? false),
            'mode' => $mode,
            'action' => 'transfer',
            'message' => (string) ($result['message'] ?? 'Transferencia solicitada.'),
            'endpoint_id' => (string) $endpoint['id'],
            'channel' => $channel,
            'destination' => $destination,
            'dial_context' => $dialContext,
        ] + $payload;
    }

    public function park(int $companyId, string $originExtensionId, string $channel, array $payload = []): array
    {
        $endpoint = $this->originEndpoint($companyId, $originExtensionId);
        if ($endpoint === null) {
            return $this->error('La identidad origen no pertenece al tenant o no existe.');
        }

        if (trim($channel) === '') {
            return $this->error('Se requiere el canal activo para estacionar la llamada.');
        }

        $result = (new AmiService($this->db))->park($companyId, $channel, [
            'timeout_ms' => (int) ($payload['timeout_ms'] ?? 45000),
            'parking_lot' => (string) ($payload['parking_lot'] ?? ''),
        ]);

        return [
            'success' => (bool) ($result['success'] ?? false),
            'mode' => 'communications-engine',
            'action' => 'park',
            'message' => (string) ($result['message'] ?? 'Park solicitado.'),
            'endpoint_id' => (string) $endpoint['id'],
            'channel' => $channel,
        ] + $payload;
    }

    public function pickup(int $companyId, string $originExtensionId, string $targetExtension, array $payload = []): array
    {
        $targetExtension = trim($targetExtension);
        if ($targetExtension === '') {
            return $this->error('Extension objetivo vacia.');
        }

        $pickupCode = trim((string) ($payload['pickup_code'] ?? '*8'));
        $destination = $pickupCode . $targetExtension;

        $result = (new PbxOriginateService($this->db))->request($companyId, $originExtensionId, $destination, [
            'requested_by_user_id' => (int) ($payload['requested_by_user_id'] ?? 0),
            'source' => (string) ($payload['source'] ?? 'web-client'),
        ]);

        return [
            'success' => (bool) ($result['queued'] ?? false),
            'mode' => 'communications-engine',
            'action' => 'pickup',
            'message' => (string) ($result['message'] ?? 'Pickup solicitado.'),
            'destination' => $destination,
            'target_extension' => $targetExtension,
        ] + $result;
    }

    private function clientSideResult(string $action, int $companyId, string $originExtensionId, array $payload, string $message): array
    {
        $endpoint = $this->originEndpoint($companyId, $originExtensionId);
        if ($endpoint === null) {
            return $this->error('La identidad origen no pertenece al tenant o no existe.');
        }

        return [
            'success' => true,
            'mode' => 'client-side',
            'action' => $action,
            'message' => $message,
            'endpoint_id' => (string) $endpoint['id'],
        ] + $payload;
    }

    private function error(string $message): array
    {
        return [
            'success' => false,
            'mode' => 'unsupported',
            'message' => $message,
        ];
    }

    private function originEndpoint(int $companyId, string $originExtensionId): ?array
    {
        $statement = $this->db->prepare(
            'SELECT id, uuid, extension_number, context, callerid
             FROM ps_endpoints
             WHERE company_id = :company_id AND deleted_at IS NULL AND (id = :value OR uuid = :value OR extension_number = :value)
             LIMIT 1'
        );
        $statement->execute(['company_id' => $companyId, 'value' => $originExtensionId]);
        $endpoint = $statement->fetch();

        return is_array($endpoint) ? $endpoint : null;
    }
}
