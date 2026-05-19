<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class ProvisioningService
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function normalizeMac(string $mac): string
    {
        return strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', $mac) ?? '');
    }

    public function generateSecret(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function deviceByMac(string $mac, ?string $secret = null): ?array
    {
        $sql =
            'SELECT d.*, c.name AS company_name, e.extension_number, e.id AS sip_username,
                    e.auth_username, e.auth_password, e.context, t.content AS template_content
             FROM provisioning_devices d
             INNER JOIN companies c ON c.id = d.company_id
             LEFT JOIN ps_endpoints e ON e.uuid = d.extension_uuid AND e.deleted_at IS NULL
             LEFT JOIN provisioning_templates t ON t.id = d.template_id AND t.deleted_at IS NULL
             WHERE d.mac_address = :mac AND d.status = "active" AND d.deleted_at IS NULL';
        $params = ['mac' => $this->normalizeMac($mac)];

        if ($secret !== null && $secret !== '') {
            $sql .= ' AND d.provisioning_secret = :secret';
            $params['secret'] = $secret;
        }

        $sql .= ' LIMIT 1';
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        $device = $statement->fetch();

        return $device !== false ? $device : null;
    }

    public function renderConfig(array $device): string
    {
        $content = (string) ($device['template_content'] ?? '');
        if ($content === '') {
            $content = $this->defaultTemplate((string) $device['vendor']);
        }

        $variables = [
            'company_id' => (string) $device['company_id'],
            'company_name' => (string) ($device['company_name'] ?? ''),
            'mac' => (string) $device['mac_address'],
            'vendor' => (string) $device['vendor'],
            'model' => (string) $device['model'],
            'firmware' => (string) ($device['firmware_version'] ?? ''),
            'extension' => (string) ($device['extension_number'] ?? ''),
            'sip_username' => (string) ($device['sip_username'] ?? ''),
            'auth_username' => (string) ($device['auth_username'] ?? ''),
            'auth_password' => (string) ($device['auth_password'] ?? ''),
            'sip_server' => (string) env('PBX_SIP_SERVER', env('APP_URL', '')),
            'display_name' => (string) ($device['display_name'] ?? $device['extension_number'] ?? ''),
            'blf_json' => (string) ($device['blf_json'] ?? '[]'),
        ];

        foreach ($variables as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }

        return $content;
    }

    public function markProvisioned(int $deviceId, string $ip, string $agent): void
    {
        $this->db->prepare(
            'UPDATE provisioning_devices
             SET last_provisioned_at = NOW(), last_ip = :ip, last_user_agent = :agent
             WHERE id = :id'
        )->execute([
            'id' => $deviceId,
            'ip' => substr($ip, 0, 45),
            'agent' => substr($agent, 0, 255),
        ]);
    }

    public function defaultTemplate(string $vendor): string
    {
        return match ($vendor) {
            'yealink' => "account.1.enable = 1\naccount.1.label = {{extension}}\naccount.1.display_name = {{display_name}}\naccount.1.user_name = {{sip_username}}\naccount.1.auth_name = {{auth_username}}\naccount.1.password = {{auth_password}}\naccount.1.sip_server.1.address = {{sip_server}}\nfeatures.direct_ip_call_enable = 0\n",
            'grandstream' => "P271=1\nP47={{sip_username}}\nP35={{auth_username}}\nP34={{auth_password}}\nP3={{display_name}}\nP270={{sip_server}}\n",
            'fanvil' => "<<VOIP CONFIG FILE>>Version:2.0000000000\n<SIP CONFIG MODULE>\n--SIP Line List--  :\nSIP1 Server Address          :{{sip_server}}\nSIP1 Phone Number            :{{sip_username}}\nSIP1 Authentication User     :{{auth_username}}\nSIP1 Authentication Password :{{auth_password}}\n",
            'poly' => "<PHONE_CONFIG>\n  <reg reg.1.address=\"{{sip_username}}\" reg.1.auth.userId=\"{{auth_username}}\" reg.1.auth.password=\"{{auth_password}}\" reg.1.server.1.address=\"{{sip_server}}\" />\n</PHONE_CONFIG>\n",
            'cisco' => "<flat-profile>\n  <Proxy_1_>{{sip_server}}</Proxy_1_>\n  <User_ID_1_>{{sip_username}}</User_ID_1_>\n  <Auth_ID_1_>{{auth_username}}</Auth_ID_1_>\n  <Password_1_>{{auth_password}}</Password_1_>\n</flat-profile>\n",
            default => "sip_server={{sip_server}}\nsip_username={{sip_username}}\nauth_username={{auth_username}}\nauth_password={{auth_password}}\n",
        };
    }

    public function contentType(string $vendor): string
    {
        return in_array($vendor, ['poly', 'cisco'], true) ? 'application/xml' : 'text/plain';
    }
}
