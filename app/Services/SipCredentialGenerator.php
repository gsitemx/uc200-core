<?php

declare(strict_types=1);

namespace App\Services;

final class SipCredentialGenerator
{
    public function forEndpoint(array $endpoint, array $settings): array
    {
        $sipDomain = trim((string) ($settings['sip_domain'] ?? ''));
        $authUser = trim((string) ($endpoint['auth_username'] ?? ''));
        $password = trim((string) ($endpoint['auth_password'] ?? ''));

        return [
            'uri' => 'sip:' . $authUser . '@' . $sipDomain,
            'authorization_username' => $authUser,
            'password' => $password,
            'display_name' => (string) ($endpoint['display_name'] ?: $endpoint['extension_number'] ?: $authUser),
            'websocket_url' => (string) ($settings['wss_url'] ?? ''),
            'transport' => (string) (($endpoint['transport'] ?? '') ?: 'transport-wss'),
            'stun_servers' => $this->stringList($settings['stun_urls'] ?? []),
            'turn_servers' => $this->stringList($settings['turn_urls'] ?? []),
            'dtls_enabled' => (string) ($settings['dtls_enabled'] ?? 'yes'),
            'ice_enabled' => (string) ($settings['ice_enabled'] ?? 'yes'),
        ];
    }

    private function stringList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(static fn ($item): string => trim((string) $item), $value)));
        }

        $decoded = json_decode((string) $value, true);
        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn ($item): string => trim((string) $item), $decoded)));
    }
}
