<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class ProvisioningService
{
    private ?array $catalog = null;

    private array $contentTypes = [
        'yealink' => 'application/octet-stream',
        'grandstream' => 'application/xml',
        'fanvil' => 'text/plain',
        'poly' => 'text/plain',
        'cisco' => 'text/plain',
    ];

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

    public function generateDeviceToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
    }

    public function deviceByMac(string $mac, ?string $secret = null): ?array
    {
        $sql =
            'SELECT d.*, c.name AS company_name, e.extension_number, e.id AS sip_username,
                    e.auth_username, e.auth_password, e.transport, e.allow, e.context,
                    t.content AS template_content, c.uuid AS company_uuid, p.entries_json AS phonebook_entries_json
             FROM provisioning_devices d
             INNER JOIN companies c ON c.id = d.company_id
             LEFT JOIN ps_endpoints e ON e.uuid = d.extension_uuid AND e.deleted_at IS NULL
             LEFT JOIN provisioning_templates t ON t.id = d.template_id AND t.deleted_at IS NULL
             LEFT JOIN provisioning_phonebooks p ON p.id = d.phonebook_id AND p.deleted_at IS NULL
             WHERE d.mac_address = :mac AND d.status = "active" AND d.deleted_at IS NULL';
        $params = ['mac' => $this->normalizeMac($mac)];

        if ($secret !== null && $secret !== '') {
            $sql .= ' AND d.provisioning_secret = :secret
                      AND (d.token_expires_at IS NULL OR d.token_expires_at >= NOW())';
            $params['secret'] = $secret;
        }

        $sql .= ' LIMIT 1';
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        $device = $statement->fetch();

        return $device !== false ? $device : null;
    }

    public function deviceByTenantAndMac(string $tenantKey, string $mac, ?string $token = null): ?array
    {
        $sql =
            'SELECT d.*, c.name AS company_name, c.uuid AS company_uuid,
                    e.extension_number, e.id AS sip_username, e.auth_username, e.auth_password,
                    e.transport, e.allow, e.context,
                    t.content AS template_content, p.entries_json AS phonebook_entries_json
             FROM provisioning_devices d
             INNER JOIN companies c ON c.id = d.company_id
             LEFT JOIN ps_endpoints e ON e.uuid = d.extension_uuid AND e.deleted_at IS NULL
             LEFT JOIN provisioning_templates t ON t.id = d.template_id AND t.deleted_at IS NULL
             LEFT JOIN provisioning_phonebooks p ON p.id = d.phonebook_id AND p.deleted_at IS NULL
             WHERE d.mac_address = :mac
               AND d.status = "active"
               AND d.deleted_at IS NULL
               AND (c.uuid = :tenant OR CAST(c.id AS CHAR) = :tenant)';
        $params = [
            'mac' => $this->normalizeMac($mac),
            'tenant' => $tenantKey,
        ];

        if ($token !== null && $token !== '') {
            $sql .= ' AND d.provisioning_secret = :token
                      AND (d.token_expires_at IS NULL OR d.token_expires_at >= NOW())';
            $params['token'] = $token;
        }

        $sql .= ' LIMIT 1';
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        $device = $statement->fetch();

        return $device !== false ? $device : null;
    }

    public function renderConfig(array $device): string
    {
        $profile = $this->resolveProfile((string) ($device['vendor'] ?? ''), (string) ($device['model'] ?? ''), (string) ($device['template_key'] ?? ''));
        $content = $this->templateContent($device, $profile);
        $defaults = $this->defaults((int) ($device['company_id'] ?? 0));
        $sipServer = $defaults['sip_server'];
        $transportId = (string) (($device['transport'] ?: '') ?: 'transport-udp');
        $transport = $this->transportLabel($transportId);
        $sipPort = $transport === 'WSS' ? '443' : ($transport === 'TLS' ? '5061' : '5060');
        $codecs = implode(',', $profile['codecs'] ?? $defaults['default_codecs']);
        $tenantKey = (string) ($device['company_uuid'] ?? $device['company_id']);
        $phonebookUrl = $this->phonebookUrl($tenantKey, $device);
        $provisioningUrl = $this->buildProvisioningUrl($device, false);
        $blfJson = $this->effectiveBlfJson($device, $profile);
        $filename = $this->configFilename($device, $profile);
        $instructions = implode("\n", $this->instructions((string) ($device['vendor'] ?? '')));

        $variables = [
            'company_id' => (string) $device['company_id'],
            'company_name' => (string) ($device['company_name'] ?? ''),
            'tenant' => $tenantKey,
            'mac' => (string) $device['mac_address'],
            'vendor' => (string) $device['vendor'],
            'model' => (string) $device['model'],
            'firmware' => (string) ($device['firmware_version'] ?? ''),
            'extension' => (string) ($device['extension_number'] ?? ''),
            'sip_username' => (string) ($device['sip_username'] ?? ''),
            'auth_username' => (string) ($device['auth_username'] ?? ''),
            'auth_password' => (string) ($device['auth_password'] ?? ''),
            'sip_server' => $sipServer,
            'sip_port' => $sipPort,
            'transport' => $transport,
            'transport_id' => $transportId,
            'codecs' => $codecs,
            'timezone' => (string) $defaults['timezone'],
            'ntp_server' => (string) $defaults['ntp_server'],
            'language' => (string) $defaults['language'],
            'provision_interval' => (string) $defaults['provision_interval'],
            'display_name' => (string) ($device['display_name'] ?? $device['extension_number'] ?? ''),
            'blf_json' => $blfJson,
            'blf_lines' => $this->renderBlfLines((string) $device['vendor'], $blfJson),
            'phonebook_url' => $phonebookUrl,
            'phonebook_entries_json' => (string) ($device['phonebook_entries_json'] ?? '[]'),
            'phonebook_lines' => $this->renderPhonebookLines((string) $device['vendor'], (string) ($device['phonebook_entries_json'] ?? '[]')),
            'provisioning_url' => $provisioningUrl,
            'generated_filename' => $filename,
            'provisioning_instructions' => $instructions,
        ];

        foreach ($variables as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }

        return $content;
    }

    public function markProvisioned(int $deviceId, string $ip, string $agent, int $statusCode = 200, string $note = 'downloaded'): void
    {
        $this->db->prepare(
            'UPDATE provisioning_devices
             SET last_provisioned_at = NOW(), last_ip = :ip, last_user_agent = :agent,
                 last_provision_status = :status, last_provision_note = :note
             WHERE id = :id'
        )->execute([
            'id' => $deviceId,
            'ip' => substr($ip, 0, 45),
            'agent' => substr($agent, 0, 255),
            'status' => (string) $statusCode,
            'note' => substr($note, 0, 255),
        ]);
    }

    public function logDownload(array $device, string $path, ?string $token, string $source, string $ip, string $agent, int $statusCode): void
    {
        $statement = $this->db->prepare(
            'INSERT INTO provisioning_download_logs
             (uuid, company_id, device_id, tenant_key, mac_address, request_path, token_fragment, ip_address, user_agent, status_code, download_source)
             VALUES
             (:uuid, :company_id, :device_id, :tenant_key, :mac_address, :request_path, :token_fragment, :ip_address, :user_agent, :status_code, :download_source)'
        );
        $statement->execute([
            'uuid' => uuid(),
            'company_id' => (int) $device['company_id'],
            'device_id' => (int) $device['id'],
            'tenant_key' => (string) ($device['company_uuid'] ?? $device['company_id']),
            'mac_address' => (string) $device['mac_address'],
            'request_path' => substr($path, 0, 255),
            'token_fragment' => $token !== null && $token !== '' ? substr($token, 0, 12) : null,
            'ip_address' => substr($ip, 0, 45),
            'user_agent' => substr($agent, 0, 255),
            'status_code' => $statusCode,
            'download_source' => $source,
        ]);
    }

    public function buildProvisioningUrl(array $device, bool $absolute = false): string
    {
        $tenantKey = (string) ($device['company_uuid'] ?? $device['company_id'] ?? '');
        $path = '/provisioning/' . rawurlencode($tenantKey) . '/' . rawurlencode((string) $device['mac_address']);
        $query = '?token=' . rawurlencode((string) $device['provisioning_secret']);

        if (! $absolute) {
            return $path . $query;
        }

        $base = rtrim((string) env('APP_URL', ''), '/');

        return $base . $path . $query;
    }

    public function defaultTemplate(string $vendor): string
    {
        return match ($vendor) {
            'yealink' => "## File: {{generated_filename}}\naccount.1.enable = 1\naccount.1.label = {{extension}}\naccount.1.display_name = {{display_name}}\naccount.1.user_name = {{sip_username}}\naccount.1.auth_name = {{auth_username}}\naccount.1.password = {{auth_password}}\naccount.1.sip_server.1.address = {{sip_server}}\naccount.1.sip_server.1.port = {{sip_port}}\naccount.1.transport = {{transport}}\naccount.1.codec.1.enable = 1\nstatic.network.time_server = {{ntp_server}}\nlocal_time.time_zone = {{timezone}}\nfeatures.direct_ip_call_enable = 0\nphone_setting.language = {{language}}\nauto_provision.repeat.minutes = {{provision_interval}}\n{{blf_lines}}\nlocal_contact.data.url = {{phonebook_url}}\n",
            'grandstream' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<gs_provision version=\"1\">\n  <item name=\"P271\">1</item>\n  <item name=\"P47\">{{sip_username}}</item>\n  <item name=\"P35\">{{auth_username}}</item>\n  <item name=\"P34\">{{auth_password}}</item>\n  <item name=\"P3\">{{display_name}}</item>\n  <item name=\"P270\">{{sip_server}}</item>\n  <item name=\"P276\">{{sip_port}}</item>\n  <item name=\"P330\">{{transport}}</item>\n  <item name=\"P64\">{{ntp_server}}</item>\n  <item name=\"P246\">{{language}}</item>\n  <item name=\"P212\">{{timezone}}</item>\n  <item name=\"P237\">{{phonebook_url}}</item>\n{{blf_lines}}\n</gs_provision>\n",
            'fanvil' => "<<VOIP CONFIG FILE>>Version:2.0000000000\n<SIP CONFIG MODULE>\n--SIP Line List--  :\nSIP1 Server Address          :{{sip_server}}\nSIP1 Server Port             :{{sip_port}}\nSIP1 Phone Number            :{{sip_username}}\nSIP1 Authentication User     :{{auth_username}}\nSIP1 Authentication Password :{{auth_password}}\nSIP1 Transport               :{{transport}}\nLanguage                     :{{language}}\nTime Zone                    :{{timezone}}\nNTP Server                   :{{ntp_server}}\nAuto Provision Repeat        :{{provision_interval}}\n{{blf_lines}}\nPhonebook URL                :{{phonebook_url}}\n",
            'poly' => "# File: {{generated_filename}}\nreg.1.server.1.address=\"{{sip_server}}\"\nreg.1.server.1.port=\"{{sip_port}}\"\nreg.1.address=\"{{sip_username}}\"\nreg.1.auth.userId=\"{{auth_username}}\"\nreg.1.auth.password=\"{{auth_password}}\"\nreg.1.displayName=\"{{display_name}}\"\ntcpIpApp.sntp.address.overrideDHCP=\"{{ntp_server}}\"\nmsg.lang=\"{{language}}\"\nvoIpProt.server.1.transport=\"{{transport}}\"\nlcl.datetime.timezone=\"{{timezone}}\"\nprov.polling.enabled=\"1\"\nprov.polling.period=\"{{provision_interval}}\"\nattendant.reg=\"1\"\n{{blf_lines}}\n",
            'cisco' => "<flat-profile>\n  <Profile_Rule>{{provisioning_url}}</Profile_Rule>\n  <Proxy_1_>{{sip_server}}</Proxy_1_>\n  <Proxy_1_Port>{{sip_port}}</Proxy_1_Port>\n  <User_ID_1_>{{sip_username}}</User_ID_1_>\n  <Auth_ID_1_>{{auth_username}}</Auth_ID_1_>\n  <Password_1_>{{auth_password}}</Password_1_>\n  <Preferred_Codec>{{codecs}}</Preferred_Codec>\n  <Time_Zone>{{timezone}}</Time_Zone>\n  <Primary_NTP_Server>{{ntp_server}}</Primary_NTP_Server>\n  {{blf_lines}}\n</flat-profile>\n",
            default => "sip_server={{sip_server}}\nsip_username={{sip_username}}\nauth_username={{auth_username}}\nauth_password={{auth_password}}\n",
        };
    }

    public function contentType(string $vendor): string
    {
        return $this->contentTypes[$vendor] ?? 'text/plain';
    }

    public function renderPhonebook(array $device): string
    {
        $entries = (string) ($device['phonebook_entries_json'] ?? '[]');

        return match ((string) $device['vendor']) {
            'poly', 'cisco' => "<phonebook>\n" . $this->renderPhonebookLines((string) $device['vendor'], $entries) . "\n</phonebook>\n",
            default => $this->renderPhonebookLines((string) $device['vendor'], $entries) . "\n",
        };
    }

    private function sipServer(): string
    {
        $server = $this->baseSipServer();
        if ($server !== '') {
            return $server;
        }

        $appUrl = (string) env('APP_URL', '');
        if ($appUrl !== '') {
            $host = parse_url($appUrl, PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                return $host;
            }
        }

        return '127.0.0.1';
    }

    private function baseSipServer(): string
    {
        return (string) env('PBX_SIP_SERVER', '');
    }

    private function phonebookUrl(string $tenantKey, array $device): string
    {
        return '/provisioning/' . rawurlencode($tenantKey) . '/' . rawurlencode((string) $device['mac_address']) . '/phonebook?token=' . rawurlencode((string) $device['provisioning_secret']);
    }

    private function renderBlfLines(string $vendor, string $json): string
    {
        $items = json_decode($json, true);
        if (! is_array($items)) {
            return '';
        }

        $lines = [];
        foreach (array_values($items) as $index => $entry) {
            if (! is_array($entry) || empty($entry['value'])) {
                continue;
            }

            $label = (string) ($entry['label'] ?? $entry['value']);
            $value = (string) $entry['value'];
            $key = (int) ($entry['key'] ?? ($index + 1));

            $lines[] = match ($vendor) {
                'yealink' => 'linekey.' . $key . '.value = ' . $value . "\nlinekey." . $key . '.label = ' . $label,
                'grandstream' => '  <item name="P' . (1360 + $key) . '">' . htmlspecialchars($value, ENT_QUOTES) . '</item>',
                'fanvil' => 'DSSKey' . $key . ' Type = blf' . "\nDSSKey" . $key . ' Value = ' . $value,
                'poly' => 'attendant.resourceList.' . $key . '.address="' . $value . '"' . "\n" . 'attendant.resourceList.' . $key . '.label="' . $label . '"',
                'cisco' => '<Unit_' . $key . '_Enable>Yes</Unit_' . $key . '_Enable><Unit_' . $key . '_User_ID>' . $value . '</Unit_' . $key . '_User_ID>',
                default => $label . '=' . $value,
            };
        }

        return implode("\n", $lines);
    }

    private function renderPhonebookLines(string $vendor, string $json): string
    {
        $items = json_decode($json, true);
        if (! is_array($items)) {
            return '';
        }

        $lines = [];
        foreach ($items as $index => $entry) {
            if (! is_array($entry) || empty($entry['number'])) {
                continue;
            }

            $name = (string) ($entry['name'] ?? 'Contact ' . ($index + 1));
            $number = (string) $entry['number'];
            $lines[] = match ($vendor) {
                'yealink', 'grandstream', 'fanvil' => $name . ',' . $number,
                'poly', 'cisco' => '<contact name="' . htmlspecialchars($name, ENT_QUOTES) . '" number="' . htmlspecialchars($number, ENT_QUOTES) . '" />',
                default => $name . '=' . $number,
            };
        }

        return implode("\n", $lines);
    }

    public function catalog(): array
    {
        if ($this->catalog !== null) {
            return $this->catalog;
        }

        return $this->catalog = require base_path('config/provisioning_devices.php');
    }

    public function brands(): array
    {
        return $this->catalog()['brands'] ?? [];
    }

    public function defaults(?int $companyId = null): array
    {
        $defaults = $this->catalog()['defaults'] ?? [];
        $defaults['sip_server'] = $defaults['sip_server'] !== '' ? $defaults['sip_server'] : $this->sipServer();

        if ($companyId !== null && $companyId > 0) {
            $statement = $this->db->prepare(
                'SELECT setting_key, setting_value
                 FROM settings
                 WHERE deleted_at IS NULL
                   AND (company_id = :company_id OR company_id IS NULL)
                   AND setting_key IN (
                       "provisioning.timezone",
                       "provisioning.ntp_server",
                       "provisioning.language",
                       "provisioning.interval",
                       "provisioning.transport",
                       "provisioning.sip_server"
                   )
                 ORDER BY company_id IS NULL'
            );
            $statement->execute(['company_id' => $companyId]);

            foreach ($statement->fetchAll() as $row) {
                $value = (string) ($row['setting_value'] ?? '');
                if ($value === '') {
                    continue;
                }

                match ((string) $row['setting_key']) {
                    'provisioning.timezone' => $defaults['timezone'] = $value,
                    'provisioning.ntp_server' => $defaults['ntp_server'] = $value,
                    'provisioning.language' => $defaults['language'] = $value,
                    'provisioning.interval' => $defaults['provision_interval'] = max(5, (int) $value),
                    'provisioning.transport' => $defaults['transport'] = strtoupper($value),
                    'provisioning.sip_server' => $defaults['sip_server'] = $value,
                    default => null,
                };
            }
        }

        return $defaults;
    }

    public function resolveProfile(string $vendor, string $model, string $templateKey = ''): array
    {
        $brands = $this->brands();
        $brand = $brands[$vendor] ?? null;
        $models = $brand['models'] ?? [];

        if ($model !== '' && isset($models[$model])) {
            return $models[$model] + ['brand' => $vendor, 'model' => $model];
        }

        foreach ($models as $name => $profile) {
            if (($profile['template_key'] ?? '') === $templateKey) {
                return $profile + ['brand' => $vendor, 'model' => $name];
            }
        }

        $firstModel = array_key_first($models);

        return ($firstModel !== null ? $models[$firstModel] : []) + ['brand' => $vendor, 'model' => $model];
    }

    public function instructions(string $vendor): array
    {
        return (array) (($this->brands()[$vendor]['instructions'] ?? []));
    }

    public function configFilename(array $device, ?array $profile = null): string
    {
        $profile ??= $this->resolveProfile((string) ($device['vendor'] ?? ''), (string) ($device['model'] ?? ''), (string) ($device['template_key'] ?? ''));
        $patterns = (array) ($profile['filenames'] ?? ['{mac}.cfg']);
        $pattern = (string) ($patterns[count($patterns) - 1] ?? '{mac}.cfg');

        return str_replace('{mac}', strtoupper((string) ($device['mac_address'] ?? 'device')), $pattern);
    }

    private function templateContent(array $device, array $profile): string
    {
        $content = (string) ($device['template_content'] ?? '');

        if ($content !== '') {
            return $content;
        }

        return $this->defaultTemplate((string) ($device['vendor'] ?? ''));
    }

    private function transportLabel(string $transportId): string
    {
        $transportId = strtolower($transportId);

        return match (true) {
            str_contains($transportId, 'wss') => 'WSS',
            str_contains($transportId, 'ws') => 'WS',
            str_contains($transportId, 'tls') => 'TLS',
            str_contains($transportId, 'tcp') => 'TCP',
            default => 'UDP',
        };
    }

    private function effectiveBlfJson(array $device, array $profile): string
    {
        $json = trim((string) ($device['blf_json'] ?? ''));
        if ($json !== '' && $json !== '[]') {
            return $json;
        }

        if (! ($profile['supports_blf'] ?? false)) {
            return '[]';
        }

        $companyId = (int) ($device['company_id'] ?? 0);
        if ($companyId <= 0) {
            return '[]';
        }

        $statement = $this->db->prepare(
            'SELECT extension_number, COALESCE(display_name, extension_number) AS label
             FROM ps_endpoints
             WHERE company_id = :company_id
               AND deleted_at IS NULL
               AND status = "active"
               AND uuid <> :extension_uuid
             ORDER BY extension_number
             LIMIT 6'
        );
        $statement->execute([
            'company_id' => $companyId,
            'extension_uuid' => (string) ($device['extension_uuid'] ?? ''),
        ]);

        $items = [];
        foreach ($statement->fetchAll() as $index => $row) {
            $items[] = [
                'key' => $index + 1,
                'label' => (string) ($row['label'] ?? $row['extension_number']),
                'value' => (string) ($row['extension_number'] ?? ''),
            ];
        }

        return json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
    }
}
