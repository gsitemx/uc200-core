<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuditService;
use App\Services\ProvisioningService;

final class ProvisioningController extends Controller
{
    public function index(Request $request): string
    {
        $service = new ProvisioningService($this->db());
        $advancedTemplates = has_role('super-admin') && (string) $request->input('advanced', '') === '1';

        return view('provisioning/index', [
            'title' => 'Provisioning',
            'devices' => $this->devices(),
            'templates' => $advancedTemplates ? $this->templates() : [],
            'phonebooks' => $this->phonebooks(),
            'downloadLogs' => $this->downloadLogs(),
            'brands' => $service->brands(),
            'advancedTemplates' => $advancedTemplates,
            'flash' => Session::flash('success'),
        ]);
    }

    public function createDevice(Request $request): string
    {
        return $this->deviceForm([
            'company_id' => has_role('super-admin') ? (int) $request->input('company_id', 0) : (int) Session::get('company_id'),
            'extension_uuid' => (string) $request->input('extension_uuid', ''),
            '_advanced' => (string) $request->input('advanced', '') === '1',
        ], '/provisioning/devices/store', 'create');
    }

    public function editDevice(Request $request): string
    {
        $device = $this->deviceFromRequest($request);
        $device['_advanced'] = (string) $request->input('advanced', '') === '1';

        return $this->deviceForm($device, '/provisioning/devices/update', 'edit');
    }

    public function storeDevice(Request $request): void
    {
        $service = new ProvisioningService($this->db());
        $data = $this->devicePayload($request, $service);
        $errors = $this->validateDevice($data);

        if ($errors !== []) {
            $this->back('/provisioning/devices/create', $errors, $data);
        }

        $this->db()->prepare(
            'INSERT INTO provisioning_devices
             (uuid, company_id, extension_uuid, template_id, template_key, phonebook_id, mac_address, vendor, model, firmware_version, display_name, provisioning_secret, generated_filename, token_expires_at, blf_json, rps_enabled, status)
             VALUES (:uuid, :company_id, :extension_uuid, :template_id, :template_key, :phonebook_id, :mac_address, :vendor, :model, :firmware_version, :display_name, :provisioning_secret, :generated_filename, :token_expires_at, :blf_json, :rps_enabled, :status)'
        )->execute(['uuid' => uuid()] + $data);

        (new AuditService($this->db()))->record('provisioning.device.created', 'provisioning_devices', null, ['mac' => $data['mac_address']], (int) $data['company_id']);
        Session::flash('success', 'Telefono agregado correctamente.');
        redirect('/provisioning');
    }

    public function updateDevice(Request $request): void
    {
        $device = $this->deviceFromRequest($request);
        $service = new ProvisioningService($this->db());
        $data = $this->devicePayload($request, $service);
        $data['company_id'] = (int) $device['company_id'];
        $errors = $this->validateDevice($data);

        if ($errors !== []) {
            $this->back('/provisioning/devices/edit?id=' . $device['uuid'], $errors, $data);
        }

        unset($data['company_id'], $data['provisioning_secret']);
        $this->db()->prepare(
            'UPDATE provisioning_devices
             SET extension_uuid = :extension_uuid, template_id = :template_id, template_key = :template_key, phonebook_id = :phonebook_id, mac_address = :mac_address,
                 vendor = :vendor, model = :model, firmware_version = :firmware_version, display_name = :display_name,
                 generated_filename = :generated_filename, token_expires_at = :token_expires_at, blf_json = :blf_json, rps_enabled = :rps_enabled, status = :status
             WHERE id = :id'
        )->execute($data + ['id' => (int) $device['id']]);

        (new AuditService($this->db()))->record('provisioning.device.updated', 'provisioning_devices', (int) $device['id'], ['mac' => $data['mac_address']], (int) $device['company_id']);
        Session::flash('success', 'Telefono actualizado correctamente.');
        redirect('/provisioning');
    }

    public function deleteDevice(Request $request): void
    {
        $device = $this->deviceFromRequest($request);
        $this->db()->prepare('UPDATE provisioning_devices SET deleted_at = NOW(), status = "inactive" WHERE id = :id')
            ->execute(['id' => (int) $device['id']]);
        (new AuditService($this->db()))->record('provisioning.device.deleted', 'provisioning_devices', (int) $device['id'], ['mac' => $device['mac_address']], (int) $device['company_id']);

        Session::flash('success', 'Telefono eliminado correctamente.');
        redirect('/provisioning');
    }

    public function rebootDevice(Request $request): void
    {
        $device = $this->deviceFromRequest($request);
        $this->db()->prepare('UPDATE provisioning_devices SET reboot_requested_at = NOW() WHERE id = :id')
            ->execute(['id' => (int) $device['id']]);
        (new AuditService($this->db()))->record('provisioning.device.reboot_requested', 'provisioning_devices', (int) $device['id'], ['mac' => $device['mac_address']], (int) $device['company_id']);

        Session::flash('success', 'Reinicio solicitado. El envio al telefono queda preparado para integracion vendor/RPS.');
        redirect('/provisioning');
    }

    public function regenerateToken(Request $request): void
    {
        $device = $this->deviceFromRequest($request);
        $service = new ProvisioningService($this->db());
        $token = $service->generateDeviceToken();

        $this->db()->prepare(
            'UPDATE provisioning_devices
             SET provisioning_secret = :token, token_expires_at = :token_expires_at
             WHERE id = :id'
        )->execute([
            'id' => (int) $device['id'],
            'token' => $token,
            'token_expires_at' => $this->nullableDateTime((string) $request->input('token_expires_at', '')),
        ]);

        (new AuditService($this->db()))->record('provisioning.device.token_regenerated', 'provisioning_devices', (int) $device['id'], ['mac' => $device['mac_address']], (int) $device['company_id']);
        Session::flash('success', 'Token de provisioning regenerado.');
        redirect('/provisioning');
    }

    public function createTemplate(Request $request): string
    {
        $this->requireProvisioningAdvanced();

        return $this->templateForm([], '/provisioning/templates/store', 'create');
    }

    public function editTemplate(Request $request): string
    {
        $this->requireProvisioningAdvanced();

        return $this->templateForm($this->templateFromRequest($request), '/provisioning/templates/update', 'edit');
    }

    public function storeTemplate(Request $request): void
    {
        $this->requireProvisioningAdvanced();
        $data = $this->templatePayload($request);
        $this->db()->prepare(
            'INSERT INTO provisioning_templates (uuid, company_id, name, vendor, model, content, status)
             VALUES (:uuid, :company_id, :name, :vendor, :model, :content, :status)'
        )->execute(['uuid' => uuid()] + $data);

        Session::flash('success', 'Plantilla creada correctamente.');
        redirect('/provisioning');
    }

    public function updateTemplate(Request $request): void
    {
        $this->requireProvisioningAdvanced();
        $template = $this->templateFromRequest($request);
        $data = $this->templatePayload($request);
        $data['company_id'] = (int) $template['company_id'];
        unset($data['company_id']);
        $this->db()->prepare(
            'UPDATE provisioning_templates SET name = :name, vendor = :vendor, model = :model, content = :content, status = :status WHERE id = :id'
        )->execute($data + ['id' => (int) $template['id']]);

        Session::flash('success', 'Plantilla actualizada correctamente.');
        redirect('/provisioning');
    }

    public function deleteTemplate(Request $request): void
    {
        $this->requireProvisioningAdvanced();
        $template = $this->templateFromRequest($request);
        $this->db()->prepare('UPDATE provisioning_templates SET deleted_at = NOW(), status = "inactive" WHERE id = :id')
            ->execute(['id' => (int) $template['id']]);

        Session::flash('success', 'Plantilla eliminada correctamente.');
        redirect('/provisioning');
    }

    public function createPhonebook(Request $request): string
    {
        return $this->phonebookForm([], '/provisioning/phonebooks/store', 'create');
    }

    public function editPhonebook(Request $request): string
    {
        return $this->phonebookForm($this->phonebookFromRequest($request), '/provisioning/phonebooks/update', 'edit');
    }

    public function storePhonebook(Request $request): void
    {
        $data = $this->phonebookPayload($request);
        $this->db()->prepare(
            'INSERT INTO provisioning_phonebooks (uuid, company_id, name, entries_json, status)
             VALUES (:uuid, :company_id, :name, :entries_json, :status)'
        )->execute(['uuid' => uuid()] + $data);

        Session::flash('success', 'Phonebook creado correctamente.');
        redirect('/provisioning');
    }

    public function updatePhonebook(Request $request): void
    {
        $phonebook = $this->phonebookFromRequest($request);
        $data = $this->phonebookPayload($request);
        $data['company_id'] = (int) $phonebook['company_id'];
        unset($data['company_id']);
        $this->db()->prepare('UPDATE provisioning_phonebooks SET name = :name, entries_json = :entries_json, status = :status WHERE id = :id')
            ->execute($data + ['id' => (int) $phonebook['id']]);

        Session::flash('success', 'Phonebook actualizado correctamente.');
        redirect('/provisioning');
    }

    public function deletePhonebook(Request $request): void
    {
        $phonebook = $this->phonebookFromRequest($request);
        $this->db()->prepare('UPDATE provisioning_phonebooks SET deleted_at = NOW(), status = "inactive" WHERE id = :id')
            ->execute(['id' => (int) $phonebook['id']]);

        Session::flash('success', 'Phonebook eliminado correctamente.');
        redirect('/provisioning');
    }

    public function config(Request $request): Response
    {
        $service = new ProvisioningService($this->db());
        $device = $service->deviceByMac((string) $request->input('mac', ''), (string) $request->input('secret', $request->input('token', '')));

        if ($device === null) {
            return new Response('Not found', 404, ['Content-Type' => 'text/plain']);
        }

        $service->markProvisioned((int) $device['id'], (string) $request->ip(), (string) $request->userAgent(), 200, 'legacy query download');
        $service->logDownload($device, $request->path(), (string) $request->input('secret', $request->input('token', '')), 'legacy_query', (string) $request->ip(), (string) $request->userAgent(), 200);

        return new Response($service->renderConfig($device), 200, [
            'Content-Type' => $service->contentType((string) $device['vendor']),
            'Content-Disposition' => 'inline; filename="' . $service->configFilename($device) . '"',
        ]);
    }

    public function configByPath(Request $request): Response
    {
        $service = new ProvisioningService($this->db());
        $tenant = (string) $request->routeParam('tenant', '');
        $mac = (string) $request->routeParam('mac', '');
        $token = (string) $request->input('token', '');
        $device = $service->deviceByTenantAndMac($tenant, $mac, $token);

        if ($device === null) {
            return new Response('Not found', 404, ['Content-Type' => 'text/plain']);
        }

        $service->markProvisioned((int) $device['id'], (string) $request->ip(), (string) $request->userAgent(), 200, 'path download');
        $service->logDownload($device, $request->path(), $token, 'path', (string) $request->ip(), (string) $request->userAgent(), 200);

        return new Response($service->renderConfig($device), 200, [
            'Content-Type' => $service->contentType((string) $device['vendor']),
            'Content-Disposition' => 'inline; filename="' . $service->configFilename($device) . '"',
        ]);
    }

    public function phonebookByPath(Request $request): Response
    {
        $service = new ProvisioningService($this->db());
        $tenant = (string) $request->routeParam('tenant', '');
        $mac = (string) $request->routeParam('mac', '');
        $token = (string) $request->input('token', '');
        $device = $service->deviceByTenantAndMac($tenant, $mac, $token);

        if ($device === null) {
            return new Response('Not found', 404, ['Content-Type' => 'text/plain']);
        }

        $service->logDownload($device, $request->path(), $token, 'path', (string) $request->ip(), (string) $request->userAgent(), 200);

        return new Response($service->renderPhonebook($device), 200, [
            'Content-Type' => $service->contentType((string) $device['vendor']),
        ]);
    }

    public function previewConfig(Request $request): Response
    {
        $device = $this->deviceFromRequest($request);
        $service = new ProvisioningService($this->db());
        $device = $service->deviceByTenantAndMac((string) ($this->companyUuid((int) $device['company_id']) ?: $device['company_id']), (string) $device['mac_address'], (string) $device['provisioning_secret']) ?? $device;

        return new Response($service->renderConfig($device), 200, [
            'Content-Type' => $service->contentType((string) ($device['vendor'] ?? 'yealink')),
            'Content-Disposition' => 'inline; filename="' . $service->configFilename($device) . '"',
        ]);
    }

    private function deviceForm(array $device, string $action, string $mode): string
    {
        $service = new ProvisioningService($this->db());
        $companyId = (int) ($device['company_id'] ?? Session::get('company_id', 0));
        $brands = $service->brands();
        $catalog = $this->modelCatalog($brands);
        $currentVendor = (string) ($device['vendor'] ?? array_key_first($brands));

        $advancedMode = has_role('super-admin') && (bool) ($device['_advanced'] ?? false);

        return view('provisioning/device-form', [
            'title' => $mode === 'create' ? 'Nuevo telefono' : 'Editar telefono',
            'device' => $device,
            'companies' => $this->companies(),
            'extensions' => $this->extensions($companyId),
            'allExtensions' => has_role('super-admin') ? $this->allExtensions() : [],
            'templates' => $advancedMode ? $this->templates() : [],
            'phonebooks' => $this->phonebooks(),
            'vendors' => array_keys($brands),
            'brands' => $brands,
            'modelCatalog' => $catalog,
            'vendorInstructions' => $service->instructions($currentVendor),
            'currentProfile' => $service->resolveProfile($currentVendor, (string) ($device['model'] ?? ''), (string) ($device['template_key'] ?? '')),
            'defaults' => $service->defaults($companyId),
            'advancedTemplates' => $advancedMode,
            'errors' => Session::flash('errors') ?? [],
            'old' => Session::flash('old') ?? [],
            'action' => $action,
            'mode' => $mode,
            'provisioningUrl' => ! empty($device['mac_address']) ? $service->buildProvisioningUrl([
                'company_uuid' => $this->companyUuid($companyId) ?? ($device['company_id'] ?? ''),
                'company_id' => $companyId,
                'mac_address' => $device['mac_address'],
                'provisioning_secret' => $device['provisioning_secret'] ?? '',
            ]) : null,
            'configFilename' => ! empty($device['mac_address']) ? $service->configFilename($device + ['company_id' => $companyId]) : null,
        ]);
    }

    private function templateForm(array $template, string $action, string $mode): string
    {
        return view('provisioning/template-form', [
            'title' => $mode === 'create' ? 'Nueva plantilla' : 'Editar plantilla',
            'template' => $template,
            'companies' => $this->companies(),
            'vendors' => array_keys((new ProvisioningService($this->db()))->brands()),
            'errors' => Session::flash('errors') ?? [],
            'old' => Session::flash('old') ?? [],
            'action' => $action,
            'mode' => $mode,
        ]);
    }

    private function phonebookForm(array $phonebook, string $action, string $mode): string
    {
        return view('provisioning/phonebook-form', [
            'title' => $mode === 'create' ? 'Nuevo phonebook' : 'Editar phonebook',
            'phonebook' => $phonebook,
            'companies' => $this->companies(),
            'errors' => Session::flash('errors') ?? [],
            'old' => Session::flash('old') ?? [],
            'action' => $action,
            'mode' => $mode,
        ]);
    }

    private function devices(): array
    {
        $sql =
            'SELECT d.*, c.name AS company_name, e.extension_number, t.name AS template_name
             FROM provisioning_devices d
             INNER JOIN companies c ON c.id = d.company_id
             LEFT JOIN ps_endpoints e ON e.uuid = d.extension_uuid
             LEFT JOIN provisioning_templates t ON t.id = d.template_id
             LEFT JOIN provisioning_phonebooks p ON p.id = d.phonebook_id
             WHERE d.deleted_at IS NULL';
        $params = [];

        if (! has_role('super-admin')) {
            $sql .= ' AND d.company_id = :company_id';
            $params['company_id'] = (int) Session::get('company_id');
        }

        $sql = str_replace('t.name AS template_name', 't.name AS template_name, p.name AS phonebook_name, c.uuid AS company_uuid', $sql);
        $sql .= ' ORDER BY c.name, d.vendor, d.model';
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function templates(): array
    {
        $sql = 'SELECT t.*, c.name AS company_name FROM provisioning_templates t INNER JOIN companies c ON c.id = t.company_id WHERE t.deleted_at IS NULL';
        $params = [];
        if (! has_role('super-admin')) {
            $sql .= ' AND t.company_id = :company_id';
            $params['company_id'] = (int) Session::get('company_id');
        }
        $sql .= ' ORDER BY t.vendor, t.name';
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function phonebooks(): array
    {
        $sql = 'SELECT p.*, c.name AS company_name FROM provisioning_phonebooks p INNER JOIN companies c ON c.id = p.company_id WHERE p.deleted_at IS NULL';
        $params = [];
        if (! has_role('super-admin')) {
            $sql .= ' AND p.company_id = :company_id';
            $params['company_id'] = (int) Session::get('company_id');
        }
        $sql .= ' ORDER BY p.name';
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function downloadLogs(): array
    {
        try {
            $sql =
                'SELECT l.*, c.name AS company_name, d.model, d.vendor
                 FROM provisioning_download_logs l
                 INNER JOIN companies c ON c.id = l.company_id
                 INNER JOIN provisioning_devices d ON d.id = l.device_id
                 WHERE 1=1';
            $params = [];
            if (! has_role('super-admin')) {
                $sql .= ' AND l.company_id = :company_id';
                $params['company_id'] = (int) Session::get('company_id');
            }
            $sql .= ' ORDER BY l.created_at DESC LIMIT 20';
            $statement = $this->db()->prepare($sql);
            $statement->execute($params);

            return $statement->fetchAll();
        } catch (\Throwable) {
            return [];
        }
    }

    private function companies(): array
    {
        if (! has_role('super-admin')) {
            return [['id' => (int) Session::get('company_id'), 'name' => (string) Session::get('company_name')]];
        }

        return $this->db()->query('SELECT id, name FROM companies WHERE deleted_at IS NULL ORDER BY name')->fetchAll();
    }

    private function extensions(int $companyId): array
    {
        $statement = $this->db()->prepare('SELECT uuid, extension_number, id FROM ps_endpoints WHERE company_id = :company_id AND deleted_at IS NULL ORDER BY extension_number');
        $statement->execute(['company_id' => $companyId]);

        return $statement->fetchAll();
    }

    private function allExtensions(): array
    {
        return $this->db()->query(
            'SELECT uuid, extension_number, id, company_id
             FROM ps_endpoints
             WHERE deleted_at IS NULL
             ORDER BY company_id, extension_number'
        )->fetchAll();
    }

    private function devicePayload(Request $request, ProvisioningService $service): array
    {
        $companyId = has_role('super-admin') ? (int) $request->input('company_id', 0) : (int) Session::get('company_id');
        $vendor = (string) $request->input('vendor', 'yealink');
        $model = trim((string) $request->input('model'));
        $profile = $service->resolveProfile($vendor, $model, '');
        $extensionUuid = trim((string) $request->input('extension_uuid')) !== '' ? trim((string) $request->input('extension_uuid')) : null;
        $phonebookId = (int) $request->input('phonebook_id', 0) > 0 ? (int) $request->input('phonebook_id') : null;
        $macAddress = $service->normalizeMac((string) $request->input('mac_address'));
        $generatedFilename = $service->configFilename([
            'vendor' => $vendor,
            'model' => $model,
            'mac_address' => $macAddress,
            'template_key' => (string) ($profile['template_key'] ?? ''),
        ], $profile);

        return [
            'company_id' => $companyId,
            'extension_uuid' => $extensionUuid,
            'template_id' => has_role('super-admin') && (int) $request->input('template_id', 0) > 0 ? (int) $request->input('template_id') : null,
            'template_key' => (string) ($profile['template_key'] ?? ''),
            'phonebook_id' => $phonebookId,
            'mac_address' => $macAddress,
            'vendor' => $vendor,
            'model' => $model,
            'firmware_version' => trim((string) $request->input('firmware_version')) !== '' ? trim((string) $request->input('firmware_version')) : null,
            'display_name' => trim((string) $request->input('display_name')) !== '' ? trim((string) $request->input('display_name')) : null,
            'provisioning_secret' => (string) $request->input('provisioning_secret', $service->generateDeviceToken()),
            'generated_filename' => $generatedFilename,
            'token_expires_at' => $this->nullableDateTime((string) $request->input('token_expires_at', '')),
            'blf_json' => trim((string) $request->input('blf_json', '[]')),
            'rps_enabled' => (string) $request->input('rps_enabled', 'no'),
            'status' => (string) $request->input('status', 'active'),
        ];
    }

    private function templatePayload(Request $request): array
    {
        return [
            'company_id' => has_role('super-admin') ? (int) $request->input('company_id', 0) : (int) Session::get('company_id'),
            'name' => trim((string) $request->input('name')),
            'vendor' => (string) $request->input('vendor', 'yealink'),
            'model' => trim((string) $request->input('model')) !== '' ? trim((string) $request->input('model')) : null,
            'content' => (string) $request->input('content', ''),
            'status' => (string) $request->input('status', 'active'),
        ];
    }

    private function phonebookPayload(Request $request): array
    {
        $entries = trim((string) $request->input('entries_json', '[]'));
        json_decode($entries, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $entries = '[]';
        }

        return [
            'company_id' => has_role('super-admin') ? (int) $request->input('company_id', 0) : (int) Session::get('company_id'),
            'name' => trim((string) $request->input('name')),
            'entries_json' => $entries,
            'status' => (string) $request->input('status', 'active'),
        ];
    }

    private function validateDevice(array $data): array
    {
        $errors = [];
        if ($data['company_id'] <= 0) {
            $errors['company_id'] = 'Selecciona una empresa.';
        }
        if (strlen((string) $data['mac_address']) !== 12) {
            $errors['mac_address'] = 'MAC address invalida.';
        }
        if (! array_key_exists((string) $data['vendor'], (new ProvisioningService($this->db()))->brands())) {
            $errors['vendor'] = 'Vendor no soportado.';
        }
        if ((string) $data['model'] === '') {
            $errors['model'] = 'Modelo requerido.';
        } elseif ((new ProvisioningService($this->db()))->resolveProfile((string) $data['vendor'], (string) $data['model'])['model'] !== (string) $data['model']) {
            $errors['model'] = 'Modelo no soportado para la marca seleccionada.';
        }
        if ($data['token_expires_at'] !== null && strtotime((string) $data['token_expires_at']) === false) {
            $errors['token_expires_at'] = 'Expiracion de token invalida.';
        }
        json_decode((string) $data['blf_json'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errors['blf_json'] = 'BLF debe ser JSON valido.';
        }

        return $errors;
    }

    private function deviceFromRequest(Request $request): array
    {
        return $this->rowFromRequest('provisioning_devices', $request);
    }

    private function templateFromRequest(Request $request): array
    {
        return $this->rowFromRequest('provisioning_templates', $request);
    }

    private function phonebookFromRequest(Request $request): array
    {
        return $this->rowFromRequest('provisioning_phonebooks', $request);
    }

    private function rowFromRequest(string $table, Request $request): array
    {
        $sql = 'SELECT * FROM ' . $table . ' WHERE uuid = :uuid AND deleted_at IS NULL';
        $params = ['uuid' => (string) $request->input('id', '')];
        if (! has_role('super-admin')) {
            $sql .= ' AND company_id = :company_id';
            $params['company_id'] = (int) Session::get('company_id');
        }
        $sql .= ' LIMIT 1';
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();

        if ($row !== false) {
            return $row;
        }

        http_response_code(404);
        echo view('errors/404', ['path' => '/provisioning']);
        exit;
    }

    private function back(string $path, array $errors, array $old): void
    {
        Session::flash('errors', $errors);
        Session::flash('old', $old);
        redirect($path);
    }

    private function nullableDateTime(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $normalized = str_replace('T', ' ', $value);
        $timestamp = strtotime($normalized);

        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }

    private function companyUuid(int $companyId): ?string
    {
        if ($companyId <= 0) {
            return null;
        }

        $statement = $this->db()->prepare('SELECT uuid FROM companies WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $companyId]);
        $uuid = $statement->fetchColumn();

        return $uuid === false ? null : (string) $uuid;
    }

    private function modelCatalog(array $brands): array
    {
        $catalog = [];

        foreach ($brands as $vendor => $brand) {
            $catalog[$vendor] = [];

            foreach ((array) ($brand['models'] ?? []) as $model => $profile) {
                $catalog[$vendor][] = [
                    'value' => $model,
                    'label' => $model,
                    'supports_blf' => (bool) ($profile['supports_blf'] ?? false),
                    'supports_remote_reboot' => (bool) ($profile['supports_remote_reboot'] ?? false),
                    'supports_phonebook' => (bool) ($profile['supports_phonebook'] ?? false),
                    'supports_tls' => (bool) ($profile['supports_tls'] ?? false),
                    'notes' => (string) ($profile['notes'] ?? ''),
                    'filenames' => (array) ($profile['filenames'] ?? []),
                ];
            }
        }

        return $catalog;
    }

    private function requireProvisioningAdvanced(): void
    {
        if (has_role('super-admin')) {
            return;
        }

        http_response_code(404);
        echo view('errors/404', ['path' => '/provisioning']);
        exit;
    }
}
