<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\AuditService;
use PDO;
use Throwable;

final class CompanyController extends Controller
{
    public function index(Request $request): string
    {
        return view('companies/index', [
            'title' => __('modules.companies'),
            'companies' => $this->companies(),
            'flash' => Session::flash('success'),
        ]);
    }

    public function create(Request $request): string
    {
        return view('companies/form', [
            'title' => __('actions.create') . ' ' . strtolower(__('fields.company')),
            'company' => [],
            'plans' => $this->plans(),
            'errors' => Session::flash('errors') ?? [],
            'old' => Session::flash('old') ?? [],
            'action' => '/companies/store',
            'mode' => 'create',
        ]);
    }

    public function store(Request $request): void
    {
        $data = $this->companyPayload($request);
        $data['admin_name'] = trim((string) $request->input('admin_name'));
        $data['admin_email'] = strtolower(trim((string) $request->input('admin_email')));
        $data['admin_password'] = (string) $request->input('admin_password');

        $errors = $this->validateCompany($data, true);

        if ($errors !== []) {
            $this->backToForm('/companies/create', $errors, $data);
        }

        $db = $this->db();

        if ($this->emailExists($db, $data['admin_email'])) {
            $this->backToForm('/companies/create', ['admin_email' => 'El correo del administrador ya existe.'], $data);
        }

        if ($data['tax_id'] !== '' && $this->taxIdExists($db, $data['tax_id'])) {
            $this->backToForm('/companies/create', ['tax_id' => 'El RFC/Tax ID ya esta registrado.'], $data);
        }

        try {
            $db->beginTransaction();
            $companyId = $this->insertCompany($db, $data);
            $roleId = $this->ensureAdminRole($db, $companyId);
            $adminId = $this->insertCompanyAdmin($db, $companyId, $data);
            $this->assignRole($db, $adminId, $roleId);
            $this->saveLicense($db, $companyId, $data);
            $this->saveSettings($db, $companyId, $data);
            $db->commit();

            (new AuditService($db))->record('company.created', 'companies', $companyId, [
                'name' => $data['name'],
                'admin_email' => $data['admin_email'],
            ], $companyId);

            Session::flash('success', 'Empresa creada correctamente.');
            redirect('/companies');
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            $this->backToForm('/companies/create', ['general' => 'No se pudo crear la empresa. Revisa los datos e intenta de nuevo.'], $data);
        }
    }

    public function show(Request $request): string
    {
        $company = $this->companyFromRequest($request);

        return view('companies/show', [
            'title' => $company['name'],
            'company' => $company,
            'stats' => $this->companyStats((int) $company['id']),
            'settings' => $this->companySettings((int) $company['id']),
        ]);
    }

    public function edit(Request $request): string
    {
        $company = $this->companyFromRequest($request);
        $company['settings'] = $this->companySettings((int) $company['id']);

        return view('companies/form', [
            'title' => __('actions.edit') . ' ' . strtolower(__('fields.company')),
            'company' => $company,
            'plans' => $this->plans(),
            'errors' => Session::flash('errors') ?? [],
            'old' => Session::flash('old') ?? [],
            'action' => '/companies/update',
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request): void
    {
        $company = $this->companyFromRequest($request);
        $data = $this->companyPayload($request);
        $errors = $this->validateCompany($data, false);

        if ($errors !== []) {
            $this->backToForm('/companies/edit?id=' . $company['uuid'], $errors, $data);
        }

        $db = $this->db();

        if ($data['tax_id'] !== '' && $this->taxIdExists($db, $data['tax_id'], (int) $company['id'])) {
            $this->backToForm('/companies/edit?id=' . $company['uuid'], ['tax_id' => 'El RFC/Tax ID ya esta registrado.'], $data);
        }

        $statement = $db->prepare(
            'UPDATE companies
             SET name = :name, legal_name = :legal_name, tax_id = :tax_id, status = :status, locale = :locale
             WHERE id = :id'
        );
        $statement->execute([
            'id' => (int) $company['id'],
            'name' => $data['name'],
            'legal_name' => $data['legal_name'] !== '' ? $data['legal_name'] : null,
            'tax_id' => $data['tax_id'] !== '' ? $data['tax_id'] : null,
            'status' => $data['status'],
            'locale' => $data['locale'],
        ]);

        $this->saveLicense($db, (int) $company['id'], $data);
        $this->saveSettings($db, (int) $company['id'], $data);
        (new AuditService($db))->record('company.updated', 'companies', (int) $company['id'], ['name' => $data['name']], (int) $company['id']);

        Session::flash('success', 'Empresa actualizada correctamente.');
        redirect('/companies');
    }

    public function destroy(Request $request): void
    {
        $company = $this->companyFromRequest($request);
        $statement = $this->db()->prepare('UPDATE companies SET deleted_at = NOW(), status = "inactive" WHERE id = :id');
        $statement->execute(['id' => (int) $company['id']]);

        (new AuditService($this->db()))->record('company.deleted', 'companies', (int) $company['id'], ['name' => $company['name']], (int) $company['id']);
        Session::flash('success', 'Empresa eliminada correctamente.');
        redirect('/companies');
    }

    public function dashboard(Request $request): string
    {
        $company = has_role('super-admin') && $request->input('id') !== null
            ? $this->companyFromRequest($request)
            : $this->currentTenantCompany();

        return view('companies/dashboard', [
            'title' => 'Dashboard de empresa',
            'company' => $company,
            'stats' => $this->companyStats((int) $company['id']),
            'settings' => $this->companySettings((int) $company['id']),
        ]);
    }

    private function companies(): array
    {
        $statement = $this->db()->query(
            'SELECT c.*, p.name AS plan_name, l.status AS license_status, l.expires_at
             FROM companies c
             LEFT JOIN company_licenses l ON l.company_id = c.id AND l.deleted_at IS NULL
             LEFT JOIN plans p ON p.id = l.plan_id
             WHERE c.deleted_at IS NULL
             ORDER BY c.created_at DESC'
        );

        return $statement->fetchAll();
    }

    private function companyFromRequest(Request $request): array
    {
        $uuid = (string) $request->input('id', '');
        $statement = $this->db()->prepare(
            'SELECT c.*, l.plan_id, l.license_key, l.status AS license_status, l.expires_at
             FROM companies c
             LEFT JOIN company_licenses l ON l.company_id = c.id AND l.deleted_at IS NULL
             WHERE c.uuid = :uuid AND c.deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute(['uuid' => $uuid]);
        $company = $statement->fetch();

        if ($company !== false) {
            return $company;
        }

        http_response_code(404);
        echo view('errors/404', ['path' => '/companies']);
        exit;
    }

    private function currentTenantCompany(): array
    {
        $company = currentCompany();

        if ($company === null) {
            http_response_code(403);
            echo view('errors/403', ['title' => 'Empresa no asignada']);
            exit;
        }

        $statement = $this->db()->prepare(
            'SELECT c.*, l.plan_id, l.license_key, l.status AS license_status, l.expires_at
             FROM companies c
             LEFT JOIN company_licenses l ON l.company_id = c.id AND l.deleted_at IS NULL
             WHERE c.id = :id AND c.deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute(['id' => (int) $company['id']]);
        $row = $statement->fetch();

        return $row === false ? $company : $row;
    }

    private function companyPayload(Request $request): array
    {
        return [
            'name' => trim((string) $request->input('name')),
            'legal_name' => trim((string) $request->input('legal_name')),
            'tax_id' => strtoupper(trim((string) $request->input('tax_id'))),
            'status' => (string) $request->input('status', 'active'),
            'locale' => $this->locale((string) $request->input('locale', 'es')),
            'plan_id' => (int) $request->input('plan_id', 0),
            'expires_at' => trim((string) $request->input('expires_at')),
            'contact_email' => strtolower(trim((string) $request->input('contact_email'))),
            'timezone' => trim((string) $request->input('timezone', 'America/Mazatlan')),
        ];
    }

    private function validateCompany(array $data, bool $creating): array
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors['name'] = 'El nombre comercial es obligatorio.';
        }

        if (! in_array($data['status'], ['active', 'inactive', 'suspended'], true)) {
            $errors['status'] = 'El estado no es valido.';
        }

        if (! in_array($data['locale'], ['es', 'en'], true)) {
            $errors['locale'] = 'El idioma no es valido.';
        }

        if ($data['contact_email'] !== '' && filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['contact_email'] = 'El correo de contacto no es valido.';
        }

        if ($creating) {
            if ($data['admin_name'] === '') {
                $errors['admin_name'] = 'El nombre del administrador es obligatorio.';
            }

            if (filter_var($data['admin_email'], FILTER_VALIDATE_EMAIL) === false) {
                $errors['admin_email'] = 'El correo del administrador no es valido.';
            }

            if (strlen($data['admin_password']) < 8) {
                $errors['admin_password'] = 'La contrasena debe tener al menos 8 caracteres.';
            }
        }

        return $errors;
    }

    private function insertCompany(PDO $db, array $data): int
    {
        $statement = $db->prepare(
            'INSERT INTO companies (uuid, name, legal_name, tax_id, status, locale)
             VALUES (:uuid, :name, :legal_name, :tax_id, :status, :locale)'
        );
        $statement->execute([
            'uuid' => uuid(),
            'name' => $data['name'],
            'legal_name' => $data['legal_name'] !== '' ? $data['legal_name'] : null,
            'tax_id' => $data['tax_id'] !== '' ? $data['tax_id'] : null,
            'status' => $data['status'],
            'locale' => $data['locale'],
        ]);

        return (int) $db->lastInsertId();
    }

    private function insertCompanyAdmin(PDO $db, int $companyId, array $data): int
    {
        $statement = $db->prepare(
            'INSERT INTO users (uuid, company_id, name, email, password_hash, is_active, locale)
             VALUES (:uuid, :company_id, :name, :email, :password_hash, 1, :locale)'
        );
        $statement->execute([
            'uuid' => uuid(),
            'company_id' => $companyId,
            'name' => $data['admin_name'],
            'email' => $data['admin_email'],
            'password_hash' => password_hash($data['admin_password'], PASSWORD_DEFAULT),
            'locale' => $data['locale'],
        ]);

        return (int) $db->lastInsertId();
    }

    private function ensureAdminRole(PDO $db, int $companyId): int
    {
        $statement = $db->prepare('SELECT id FROM roles WHERE company_id = :company_id AND slug = "admin-empresa" AND deleted_at IS NULL LIMIT 1');
        $statement->execute(['company_id' => $companyId]);
        $roleId = $statement->fetchColumn();

        if ($roleId !== false) {
            return (int) $roleId;
        }

        $statement = $db->prepare(
            'INSERT INTO roles (uuid, company_id, name, slug, description, is_system)
             VALUES (:uuid, :company_id, "ADMIN_EMPRESA", "admin-empresa", "Administrador principal de empresa", 1)'
        );
        $statement->execute(['uuid' => uuid(), 'company_id' => $companyId]);

        $roleId = (int) $db->lastInsertId();
        $permissions = $db->query('SELECT id FROM permissions WHERE slug IN ("core.dashboard.view", "companies.dashboard.view", "crm.call", "pbx.originate", "call.hold", "call.transfer", "call.pickup", "call.park", "call.supervise", "security.view", "security.manage")')->fetchAll();
        $assign = $db->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)');

        foreach ($permissions as $permission) {
            $assign->execute(['role_id' => $roleId, 'permission_id' => (int) $permission['id']]);
        }

        return $roleId;
    }

    private function assignRole(PDO $db, int $userId, int $roleId): void
    {
        $statement = $db->prepare('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)');
        $statement->execute(['user_id' => $userId, 'role_id' => $roleId]);
    }

    private function saveLicense(PDO $db, int $companyId, array $data): void
    {
        if ($data['plan_id'] <= 0) {
            return;
        }

        $current = $db->prepare('SELECT id FROM company_licenses WHERE company_id = :company_id AND deleted_at IS NULL LIMIT 1');
        $current->execute(['company_id' => $companyId]);
        $licenseId = $current->fetchColumn();

        if ($licenseId !== false) {
            $statement = $db->prepare(
                'UPDATE company_licenses
                 SET plan_id = :plan_id, status = "active", expires_at = :expires_at
                 WHERE id = :id'
            );
            $statement->execute([
                'id' => (int) $licenseId,
                'plan_id' => $data['plan_id'],
                'expires_at' => $data['expires_at'] !== '' ? $data['expires_at'] . ' 23:59:59' : null,
            ]);
            return;
        }

        $statement = $db->prepare(
            'INSERT INTO company_licenses (uuid, company_id, plan_id, license_key, status, starts_at, expires_at)
             VALUES (:uuid, :company_id, :plan_id, :license_key, "active", NOW(), :expires_at)'
        );
        $statement->execute([
            'uuid' => uuid(),
            'company_id' => $companyId,
            'plan_id' => $data['plan_id'],
            'license_key' => 'UC200-' . strtoupper(bin2hex(random_bytes(6))),
            'expires_at' => $data['expires_at'] !== '' ? $data['expires_at'] . ' 23:59:59' : null,
        ]);
    }

    private function saveSettings(PDO $db, int $companyId, array $data): void
    {
        $this->upsertSetting($db, $companyId, 'company.contact_email', $data['contact_email']);
        $this->upsertSetting($db, $companyId, 'company.timezone', $data['timezone'] !== '' ? $data['timezone'] : 'America/Mazatlan');
    }

    private function upsertSetting(PDO $db, int $companyId, string $key, string $value): void
    {
        $statement = $db->prepare(
            'INSERT INTO settings (uuid, company_id, setting_key, setting_value, is_public)
             VALUES (:uuid, :company_id, :setting_key, :setting_value, 0)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), deleted_at = NULL'
        );
        $statement->execute([
            'uuid' => uuid(),
            'company_id' => $companyId,
            'setting_key' => $key,
            'setting_value' => json_encode($value, JSON_THROW_ON_ERROR),
        ]);
    }

    private function companyStats(int $companyId): array
    {
        $db = $this->db();

        return [
            'users' => $this->countByCompany($db, 'users', $companyId),
            'audit_logs' => $this->countByCompany($db, 'audit_logs', $companyId),
            'features' => $this->countByCompany($db, 'company_features', $companyId),
        ];
    }

    private function countByCompany(PDO $db, string $table, int $companyId): int
    {
        $softDeletedTables = ['users', 'company_features'];
        $sql = 'SELECT COUNT(*) FROM ' . $table . ' WHERE company_id = :company_id';

        if (in_array($table, $softDeletedTables, true)) {
            $sql .= ' AND deleted_at IS NULL';
        }

        $statement = $db->prepare($sql);
        $statement->execute(['company_id' => $companyId]);

        return (int) $statement->fetchColumn();
    }

    private function companySettings(int $companyId): array
    {
        $statement = $this->db()->prepare(
            'SELECT setting_key, setting_value FROM settings WHERE company_id = :company_id AND deleted_at IS NULL ORDER BY setting_key'
        );
        $statement->execute(['company_id' => $companyId]);

        $settings = [];
        foreach ($statement->fetchAll() as $row) {
            $settings[$row['setting_key']] = json_decode((string) $row['setting_value'], true);
        }

        return $settings;
    }

    private function plans(): array
    {
        return $this->db()->query(
            'SELECT id, name, slug FROM plans WHERE is_active = 1 AND deleted_at IS NULL ORDER BY name'
        )->fetchAll();
    }

    private function emailExists(PDO $db, string $email): bool
    {
        $statement = $db->prepare('SELECT COUNT(*) FROM users WHERE email = :email AND deleted_at IS NULL');
        $statement->execute(['email' => $email]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function taxIdExists(PDO $db, string $taxId, ?int $ignoreCompanyId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM companies WHERE tax_id = :tax_id AND deleted_at IS NULL';
        $params = ['tax_id' => $taxId];

        if ($ignoreCompanyId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreCompanyId;
        }

        $statement = $db->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }

    private function backToForm(string $path, array $errors, array $old): void
    {
        Session::flash('errors', $errors);
        Session::flash('old', $old);
        redirect($path);
    }

    private function locale(string $locale): string
    {
        $locale = strtolower(substr($locale, 0, 2));

        return in_array($locale, ['es', 'en'], true) ? $locale : 'es';
    }
}
