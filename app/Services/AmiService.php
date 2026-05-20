<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class AmiService
{
    private readonly AmiSettingsService $settings;

    public function __construct(
        private readonly PDO $db,
        ?AmiSettingsService $settings = null,
    ) {
        $this->settings = $settings ?? new AmiSettingsService($db);
    }

    public function status(?int $companyId = null): array
    {
        $settings = $this->settings->forCompany($companyId, true);
        if (($settings['enabled'] ?? 'no') !== 'yes') {
            return [
                'enabled' => false,
                'connected' => false,
                'message' => 'AMI deshabilitado.',
                'host' => $settings['host'],
                'port' => (int) $settings['port'],
                'username' => $settings['username'],
                'password_configured' => $settings['password_configured'],
                'secure_storage' => $settings['secure_storage'],
            ];
        }

        if (($settings['username'] ?? '') === '' || ($settings['password'] ?? '') === '') {
            return [
                'enabled' => true,
                'connected' => false,
                'message' => 'Faltan credenciales AMI.',
                'host' => $settings['host'],
                'port' => (int) $settings['port'],
                'username' => $settings['username'],
                'password_configured' => $settings['password_configured'],
                'secure_storage' => $settings['secure_storage'],
            ];
        }

        $startedAt = microtime(true);
        try {
            $connection = $this->connect($settings);
            $packet = $this->sendAction($connection['stream'], [
                'Action' => 'Ping',
            ]);
            fclose($connection['stream']);

            return [
                'enabled' => true,
                'connected' => true,
                'message' => (string) ($packet['Message'] ?? 'AMI conectado.'),
                'host' => $settings['host'],
                'port' => (int) $settings['port'],
                'username' => $settings['username'],
                'password_configured' => $settings['password_configured'],
                'secure_storage' => $settings['secure_storage'],
                'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'banner' => $connection['banner'],
            ];
        } catch (RuntimeException $exception) {
            return [
                'enabled' => true,
                'connected' => false,
                'message' => $exception->getMessage(),
                'host' => $settings['host'],
                'port' => (int) $settings['port'],
                'username' => $settings['username'],
                'password_configured' => $settings['password_configured'],
                'secure_storage' => $settings['secure_storage'],
            ];
        }
    }

    public function originate(int $companyId, string $endpointId, string $destination, array $options = []): array
    {
        $settings = $this->settings->forCompany($companyId, true);
        if (($settings['enabled'] ?? 'no') !== 'yes') {
            return [
                'queued' => false,
                'supported' => false,
                'simulated' => false,
                'message' => 'AMI deshabilitado para este tenant.',
            ];
        }

        $connection = $this->connect($settings);
        try {
            $packet = $this->sendAction($connection['stream'], [
                'Action' => 'Originate',
                'Channel' => $this->channelName($endpointId),
                'Context' => (string) ($options['context'] ?? ($settings['originate_context'] ?? '')),
                'Exten' => $destination,
                'Priority' => (string) ((int) ($options['priority'] ?? 1)),
                'CallerID' => (string) ($options['callerid'] ?? ''),
                'Timeout' => (string) ((int) ($options['timeout_ms'] ?? 30000)),
                'Async' => 'true',
                'Variable' => $this->variables($companyId, $endpointId, $destination, $options),
            ]);
        } finally {
            fclose($connection['stream']);
        }

        return [
            'queued' => strtoupper((string) ($packet['Response'] ?? '')) === 'SUCCESS',
            'supported' => true,
            'simulated' => false,
            'response' => $packet['Response'] ?? null,
            'message' => (string) ($packet['Message'] ?? 'AMI originate enviado.'),
            'action_id' => $packet['ActionID'] ?? null,
            'channel' => $this->channelName($endpointId),
            'destination' => $destination,
            'context' => (string) ($options['context'] ?? ($settings['originate_context'] ?? '')),
        ];
    }

    public function hangup(?int $companyId, string $channel): array
    {
        $settings = $this->settings->forCompany($companyId, true);
        $connection = $this->connect($settings);
        try {
            $packet = $this->sendAction($connection['stream'], [
                'Action' => 'Hangup',
                'Channel' => trim($channel),
            ]);
        } finally {
            fclose($connection['stream']);
        }

        return [
            'success' => strtoupper((string) ($packet['Response'] ?? '')) === 'SUCCESS',
            'message' => (string) ($packet['Message'] ?? 'Hangup enviado.'),
            'action_id' => $packet['ActionID'] ?? null,
        ];
    }

    public function redirect(?int $companyId, string $channel, string $context, string $extension, int $priority = 1, ?string $extraChannel = null): array
    {
        $settings = $this->settings->forCompany($companyId, true);
        $connection = $this->connect($settings);
        try {
            $packet = $this->sendAction($connection['stream'], [
                'Action' => 'Redirect',
                'Channel' => trim($channel),
                'Context' => trim($context),
                'Exten' => trim($extension),
                'Priority' => (string) max(1, $priority),
                'ExtraChannel' => $extraChannel !== null ? trim($extraChannel) : null,
            ]);
        } finally {
            fclose($connection['stream']);
        }

        return [
            'success' => strtoupper((string) ($packet['Response'] ?? '')) === 'SUCCESS',
            'message' => (string) ($packet['Message'] ?? 'Redirect enviado.'),
            'action_id' => $packet['ActionID'] ?? null,
        ];
    }

    public function park(?int $companyId, string $channel, array $options = []): array
    {
        $settings = $this->settings->forCompany($companyId, true);
        $connection = $this->connect($settings);
        try {
            $packet = $this->sendAction($connection['stream'], [
                'Action' => 'Park',
                'Channel' => trim($channel),
                'Channel2' => trim((string) ($options['channel2'] ?? '')),
                'Timeout' => (string) max(1, (int) ($options['timeout_ms'] ?? 45000)),
                'Parkinglot' => trim((string) ($options['parking_lot'] ?? '')),
            ]);
        } finally {
            fclose($connection['stream']);
        }

        return [
            'success' => strtoupper((string) ($packet['Response'] ?? '')) === 'SUCCESS',
            'message' => (string) ($packet['Message'] ?? 'Park enviado.'),
            'action_id' => $packet['ActionID'] ?? null,
        ];
    }

    public function playDtmf(?int $companyId, string $channel, string $digit): array
    {
        $settings = $this->settings->forCompany($companyId, true);
        $connection = $this->connect($settings);
        try {
            $packet = $this->sendAction($connection['stream'], [
                'Action' => 'PlayDTMF',
                'Channel' => trim($channel),
                'Digit' => substr(trim($digit), 0, 1),
            ]);
        } finally {
            fclose($connection['stream']);
        }

        return [
            'success' => strtoupper((string) ($packet['Response'] ?? '')) === 'SUCCESS',
            'message' => (string) ($packet['Message'] ?? 'DTMF enviado.'),
            'action_id' => $packet['ActionID'] ?? null,
        ];
    }

    public function listen(?int $companyId, callable $handler): void
    {
        throw new RuntimeException('AMI event listener preparado para fase futura. Falta worker dedicado.');
    }

    private function connect(array $settings): array
    {
        $timeout = max(1, (int) ($settings['connect_timeout'] ?? 3));
        $target = sprintf('tcp://%s:%d', $settings['host'], (int) $settings['port']);
        $stream = @stream_socket_client($target, $errno, $error, $timeout);

        if (! is_resource($stream)) {
            throw new RuntimeException('No se pudo conectar a AMI: ' . ($error !== '' ? $error : 'conexion rechazada.'));
        }

        stream_set_timeout($stream, $timeout);
        $banner = trim((string) fgets($stream));
        $login = $this->sendAction($stream, [
            'Action' => 'Login',
            'Username' => (string) $settings['username'],
            'Secret' => (string) $settings['password'],
            'Events' => 'off',
        ]);

        if (strtoupper((string) ($login['Response'] ?? '')) !== 'SUCCESS') {
            fclose($stream);
            throw new RuntimeException((string) ($login['Message'] ?? 'AMI login rechazado.'));
        }

        return [
            'stream' => $stream,
            'banner' => $banner,
        ];
    }

    private function sendAction($stream, array $fields): array
    {
        $actionId = 'uc200-' . bin2hex(random_bytes(8));
        $fields['ActionID'] = $actionId;
        $payload = $this->serializeAction($fields);

        if (@fwrite($stream, $payload) === false) {
            throw new RuntimeException('No se pudo escribir en el socket AMI.');
        }

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $packet = $this->readPacket($stream);
            if ($packet === []) {
                continue;
            }
            if (($packet['ActionID'] ?? null) === $actionId || isset($packet['Response'])) {
                return $packet;
            }
        }

        throw new RuntimeException('AMI no respondio a tiempo.');
    }

    private function serializeAction(array $fields): string
    {
        $lines = [];

        foreach ($fields as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    if ($item !== null && $item !== '') {
                        $lines[] = $key . ': ' . $item;
                    }
                }
                continue;
            }

            $lines[] = $key . ': ' . $value;
        }

        return implode("\r\n", $lines) . "\r\n\r\n";
    }

    private function readPacket($stream): array
    {
        $packet = [];

        while (($line = fgets($stream)) !== false) {
            $line = rtrim($line, "\r\n");
            if ($line === '') {
                break;
            }

            if (! str_contains($line, ':')) {
                continue;
            }

            [$key, $value] = explode(':', $line, 2);
            $key = trim($key);
            $value = ltrim($value);

            if (isset($packet[$key])) {
                $packet[$key] = is_array($packet[$key])
                    ? [...$packet[$key], $value]
                    : [$packet[$key], $value];
                continue;
            }

            $packet[$key] = $value;
        }

        return $packet;
    }

    private function channelName(string $endpointId): string
    {
        $endpointId = trim($endpointId);

        return str_contains($endpointId, '/') ? $endpointId : 'PJSIP/' . $endpointId;
    }

    private function variables(int $companyId, string $endpointId, string $destination, array $options): array
    {
        $variables = [
            'UC200_COMPANY_ID=' . $companyId,
            'UC200_ORIGIN_ENDPOINT=' . $endpointId,
            'UC200_DESTINATION=' . $destination,
        ];

        foreach (($options['variables'] ?? []) as $key => $value) {
            if ($value !== null && $value !== '') {
                $variables[] = $key . '=' . $value;
            }
        }

        return $variables;
    }
}
