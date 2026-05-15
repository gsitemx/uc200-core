<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\AuditService;
use App\Services\LicenseService;
use PDO;

final class LicensingController extends Controller
{
    public function dashboard(Request $request): string
    {
        $service = new LicenseService($this->db());
        $expired = $service->expireOldLicenses();

        if ($expired > 0) {
            Session::flash('success', $expired . ' licencia(s) vencidas fueron marcadas como expiradas.');
        }

        return view('licensing/dashboard', [
            'title' => 'Licensing',
            'flash' => Session::flash('success'),
            'cards' => [
                ['label' => 'Planes', 'value' => $this->count('plans'), 'hint' => 'Paquetes comerciales activos e historicos'],
                ['label' => 'Features', 'value' => $this->count('features'), 'hint' => 'Capacidades disponibles para planes'],
                ['label' => 'Licencias activas', 'value' => $this->countLicenses('active'), 'hint' => 'Empresas con licencia vigente'],
                ['label' => 'Por vencer', 'value' => $this->expiringLicenses(), 'hint' => 'Vencen en los proximos 30 dias'],
            ],
            'licenses' => $this->recentLicenses(),
        ]);
    }

    public function plans(Request $request): string
    {
        return view('licensing/plans/index', [
            'title' => 'Planes',
            'plans' => $this->allPlans(),
            'flash' => Session::flash('success'),
        ]);
    }

    public function createPlan(Request $request): string
    {
        return view('licensing/plans/form', [
            'title' => 'Nuevo plan',
            'plan' => [],
            'features' => $this->allFeatures(),
            'enabledFeatures' => [],
            'errors' => Session::flash('errors') ?? [],
            'old' => Session::flash('old') ?? [],
            'action' => '/licensing/plans/store',
            'mode' => 'create',
        ]);
    }

    public function storePlan(Request $request): void
    {
        $data = $this->planPayload($request);
        $errors = $this->validatePlan($data);

        if ($errors !== []) {
            $this->back('/licensing/plans/create', $errors, $data);
        }

        $db = $this->db();
        $statement = $db->prepare(
            'INSERT INTO plans (uuid, name, slug, description, price, billing_period, is_active)
             VALUES (:uuid, :name, :slug, :description, :price, :billing_period, :is_active)'
        );
        $statement->execute([
            'uuid' => uuid(),
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] !== '' ? $data['description'] : null,
            'price' => $data['price'],
            'billing_period' => $data['billing_period'],
            'is_active' => $data['is_active'],
        ]);
        $planId = (int) $db->lastInsertId();
        $this->syncPlanFeatures($db, $planId, $request->input('features', []));
        (new AuditService($db))->record('licensing.plan.created', 'plans', $planId, ['slug' => $data['slug']]);

        Session::flash('success', 'Plan creado correctamente.');
        redirect('/licensing/plans');
    }

    public function editPlan(Request $request): string
    {
        $plan = $this->planFromRequest($request);

        return view('licensing/plans/form', [
            'title' => 'Editar plan',
            'plan' => $plan,
            'features' => $this->allFeatures(),
            'enabledFeatures' => $this->planFeatureIds((int) $plan['id']),
            'errors' => Session::flash('errors') ?? [],
            'old' => Session::flash('old') ?? [],
            'action' => '/licensing/plans/update',
            'mode' => 'edit',
        ]);
    }

    public function updatePlan(Request $request): void
    {
        $plan = $this->planFromRequest($request);
        $data = $this->planPayload($request);
        $errors = $this->validatePlan($data);

        if ($errors !== []) {
            $this->back('/licensing/plans/edit?id=' . $plan['uuid'], $errors, $data);
        }

        $db = $this->db();
        $statement = $db->prepare(
            'UPDATE plans
             SET name = :name, slug = :slug, description = :description, price = :price,
                 billing_period = :billing_period, is_active = :is_active
             WHERE id = :id'
        );
        $statement->execute([
            'id' => (int) $plan['id'],
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] !== '' ? $data['description'] : null,
            'price' => $data['price'],
            'billing_period' => $data['billing_period'],
            'is_active' => $data['is_active'],
        ]);
        $this->syncPlanFeatures($db, (int) $plan['id'], $request->input('features', []));
        (new AuditService($db))->record('licensing.plan.updated', 'plans', (int) $plan['id'], ['slug' => $data['slug']]);

        Session::flash('success', 'Plan actualizado correctamente.');
        redirect('/licensing/plans');
    }

    public function deletePlan(Request $request): void
    {
        $plan = $this->planFromRequest($request);
        $this->softDelete('plans', (int) $plan['id']);
        (new AuditService($this->db()))->record('licensing.plan.deleted', 'plans', (int) $plan['id'], ['slug' => $plan['slug']]);
        Session::flash('success', 'Plan eliminado correctamente.');
        redirect('/licensing/plans');
    }

    public function features(Request $request): string
    {
        return view('licensing/features/index', [
            'title' => 'Features',
            'features' => $this->featuresWithGroup(),
            'flash' => Session::flash('success'),
        ]);
    }

    public function createFeature(Request $request): string
    {
        return view('licensing/features/form', [
            'title' => 'Nueva feature',
            'feature' => [],
            'groups' => $this->featureGroups(),
            'errors' => Session::flash('errors') ?? [],
            'old' => Session::flash('old') ?? [],
            'action' => '/licensing/features/store',
            'mode' => 'create',
        ]);
    }

    public function storeFeature(Request $request): void
    {
        $data = $this->featurePayload($request);
        $errors = $this->validateFeature($data);

        if ($errors !== []) {
            $this->back('/licensing/features/create', $errors, $data);
        }

        $db = $this->db();
        $statement = $db->prepare(
            'INSERT INTO features (uuid, feature_group_id, module, name, slug, description, is_active)
             VALUES (:uuid, :feature_group_id, :module, :name, :slug, :description, :is_active)'
        );
        $statement->execute([
            'uuid' => uuid(),
            'feature_group_id' => $data['feature_group_id'] > 0 ? $data['feature_group_id'] : null,
            'module' => $data['module'],
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] !== '' ? $data['description'] : null,
            'is_active' => $data['is_active'],
        ]);
        $featureId = (int) $db->lastInsertId();
        (new AuditService($db))->record('licensing.feature.created', 'features', $featureId, ['slug' => $data['slug']]);

        Session::flash('success', 'Feature creada correctamente.');
        redirect('/licensing/features');
    }

    public function editFeature(Request $request): string
    {
        $feature = $this->featureFromRequest($request);

        return view('licensing/features/form', [
            'title' => 'Editar feature',
            'feature' => $feature,
            'groups' => $this->featureGroups(),
            'errors' => Session::flash('errors') ?? [],
            'old' => Session::flash('old') ?? [],
            'action' => '/licensing/features/update',
            'mode' => 'edit',
        ]);
    }

    public function updateFeature(Request $request): void
    {
        $feature = $this->featureFromRequest($request);
        $data = $this->featurePayload($request);
        $errors = $this->validateFeature($data);

        if ($errors !== []) {
            $this->back('/licensing/features/edit?id=' . $feature['uuid'], $errors, $data);
        }

        $statement = $this->db()->prepare(
            'UPDATE features
             SET feature_group_id = :feature_group_id, module = :module, name = :name,
                 slug = :slug, description = :description, is_active = :is_active
             WHERE id = :id'
        );
        $statement->execute([
            'id' => (int) $feature['id'],
            'feature_group_id' => $data['feature_group_id'] > 0 ? $data['feature_group_id'] : null,
            'module' => $data['module'],
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] !== '' ? $data['description'] : null,
            'is_active' => $data['is_active'],
        ]);
        (new AuditService($this->db()))->record('licensing.feature.updated', 'features', (int) $feature['id'], ['slug' => $data['slug']]);

        Session::flash('success', 'Feature actualizada correctamente.');
        redirect('/licensing/features');
    }

    public function deleteFeature(Request $request): void
    {
        $feature = $this->featureFromRequest($request);
        $this->softDelete('features', (int) $feature['id']);
        (new AuditService($this->db()))->record('licensing.feature.deleted', 'features', (int) $feature['id'], ['slug' => $feature['slug']]);
        Session::flash('success', 'Feature eliminada correctamente.');
        redirect('/licensing/features');
    }

    public function licenses(Request $request): string
    {
        return view('licensing/licenses/index', [
            'title' => 'Licencias',
            'licenses' => $this->allLicenses(),
            'flash' => Session::flash('success'),
        ]);
    }

    public function createLicense(Request $request): string
    {
        return view('licensing/licenses/form', [
            'title' => 'Nueva licencia',
            'license' => [],
            'limits' => [],
            'companies' => $this->companies(),
            'plans' => $this->allPlans(),
            'errors' => Session::flash('errors') ?? [],
            'old' => Session::flash('old') ?? [],
            'action' => '/licensing/licenses/store',
            'mode' => 'create',
        ]);
    }

    public function storeLicense(Request $request): void
    {
        $data = $this->licensePayload($request);
        $errors = $this->validateLicense($data);

        if ($errors !== []) {
            $this->back('/licensing/licenses/create', $errors, $data);
        }

        $db = $this->db();
        $statement = $db->prepare(
            'INSERT INTO company_licenses (uuid, company_id, plan_id, license_key, status, starts_at, expires_at)
             VALUES (:uuid, :company_id, :plan_id, :license_key, :status, :starts_at, :expires_at)'
        );
        $statement->execute([
            'uuid' => uuid(),
            'company_id' => $data['company_id'],
            'plan_id' => $data['plan_id'],
            'license_key' => $data['license_key'] !== '' ? $data['license_key'] : $this->licenseKey(),
            'status' => $data['status'],
            'starts_at' => $data['starts_at'] . ' 00:00:00',
            'expires_at' => $data['expires_at'] !== '' ? $data['expires_at'] . ' 23:59:59' : null,
        ]);
        $licenseId = (int) $db->lastInsertId();
        $this->syncLimits($db, $licenseId, $request);
        (new LicenseService($db))->clearCache((int) $data['company_id']);
        (new AuditService($db))->record('licensing.license.created', 'company_licenses', $licenseId, ['company_id' => $data['company_id']], (int) $data['company_id']);

        Session::flash('success', 'Licencia creada correctamente.');
        redirect('/licensing/licenses');
    }

    public function editLicense(Request $request): string
    {
        $license = $this->licenseFromRequest($request);

        return view('licensing/licenses/form', [
            'title' => 'Editar licencia',
            'license' => $license,
            'limits' => $this->licenseLimits((int) $license['id']),
            'companies' => $this->companies(),
            'plans' => $this->allPlans(),
            'errors' => Session::flash('errors') ?? [],
            'old' => Session::flash('old') ?? [],
            'action' => '/licensing/licenses/update',
            'mode' => 'edit',
        ]);
    }

    public function updateLicense(Request $request): void
    {
        $license = $this->licenseFromRequest($request);
        $data = $this->licensePayload($request);
        $errors = $this->validateLicense($data);

        if ($errors !== []) {
            $this->back('/licensing/licenses/edit?id=' . $license['uuid'], $errors, $data);
        }

        $db = $this->db();
        $statement = $db->prepare(
            'UPDATE company_licenses
             SET company_id = :company_id, plan_id = :plan_id, license_key = :license_key,
                 status = :status, starts_at = :starts_at, expires_at = :expires_at
             WHERE id = :id'
        );
        $statement->execute([
            'id' => (int) $license['id'],
            'company_id' => $data['company_id'],
            'plan_id' => $data['plan_id'],
            'license_key' => $data['license_key'],
            'status' => $data['status'],
            'starts_at' => $data['starts_at'] . ' 00:00:00',
            'expires_at' => $data['expires_at'] !== '' ? $data['expires_at'] . ' 23:59:59' : null,
        ]);
        $this->syncLimits($db, (int) $license['id'], $request);
        (new LicenseService($db))->clearCache((int) $data['company_id']);
        (new AuditService($db))->record('licensing.license.updated', 'company_licenses', (int) $license['id'], ['company_id' => $data['company_id']], (int) $data['company_id']);

        Session::flash('success', 'Licencia actualizada correctamente.');
        redirect('/licensing/licenses');
    }

    public function deleteLicense(Request $request): void
    {
        $license = $this->licenseFromRequest($request);
        $this->softDelete('company_licenses', (int) $license['id']);
        (new LicenseService($this->db()))->clearCache((int) $license['company_id']);
        (new AuditService($this->db()))->record('licensing.license.deleted', 'company_licenses', (int) $license['id'], ['license_key' => $license['license_key']], (int) $license['company_id']);
        Session::flash('success', 'Licencia eliminada correctamente.');
        redirect('/licensing/licenses');
    }

    private function count(string $table): int
    {
        return (int) $this->db()->query('SELECT COUNT(*) FROM ' . $table . ' WHERE deleted_at IS NULL')->fetchColumn();
    }

    private function countLicenses(string $status): int
    {
        $statement = $this->db()->prepare('SELECT COUNT(*) FROM company_licenses WHERE status = :status AND deleted_at IS NULL');
        $statement->execute(['status' => $status]);

        return (int) $statement->fetchColumn();
    }

    private function expiringLicenses(): int
    {
        return (int) $this->db()->query(
            'SELECT COUNT(*) FROM company_licenses
             WHERE status = "active" AND deleted_at IS NULL
               AND expires_at IS NOT NULL
               AND expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)'
        )->fetchColumn();
    }

    private function recentLicenses(): array
    {
        return $this->db()->query(
            'SELECT cl.*, c.name AS company_name, p.name AS plan_name
             FROM company_licenses cl
             INNER JOIN companies c ON c.id = cl.company_id
             INNER JOIN plans p ON p.id = cl.plan_id
             WHERE cl.deleted_at IS NULL
             ORDER BY cl.created_at DESC
             LIMIT 8'
        )->fetchAll();
    }

    private function allPlans(): array
    {
        return $this->db()->query('SELECT * FROM plans WHERE deleted_at IS NULL ORDER BY name')->fetchAll();
    }

    private function allFeatures(): array
    {
        return $this->db()->query('SELECT * FROM features WHERE deleted_at IS NULL ORDER BY module, name')->fetchAll();
    }

    private function featuresWithGroup(): array
    {
        return $this->db()->query(
            'SELECT f.*, fg.name AS group_name
             FROM features f
             LEFT JOIN feature_groups fg ON fg.id = f.feature_group_id
             WHERE f.deleted_at IS NULL
             ORDER BY fg.sort_order, f.module, f.name'
        )->fetchAll();
    }

    private function featureGroups(): array
    {
        return $this->db()->query('SELECT * FROM feature_groups WHERE deleted_at IS NULL ORDER BY sort_order, name')->fetchAll();
    }

    private function companies(): array
    {
        return $this->db()->query('SELECT id, name FROM companies WHERE deleted_at IS NULL ORDER BY name')->fetchAll();
    }

    private function allLicenses(): array
    {
        return $this->db()->query(
            'SELECT cl.*, c.name AS company_name, p.name AS plan_name
             FROM company_licenses cl
             INNER JOIN companies c ON c.id = cl.company_id
             INNER JOIN plans p ON p.id = cl.plan_id
             WHERE cl.deleted_at IS NULL
             ORDER BY cl.created_at DESC'
        )->fetchAll();
    }

    private function planFromRequest(Request $request): array
    {
        return $this->rowByUuid('plans', (string) $request->input('id', ''));
    }

    private function featureFromRequest(Request $request): array
    {
        return $this->rowByUuid('features', (string) $request->input('id', ''));
    }

    private function licenseFromRequest(Request $request): array
    {
        return $this->rowByUuid('company_licenses', (string) $request->input('id', ''));
    }

    private function rowByUuid(string $table, string $uuid): array
    {
        $statement = $this->db()->prepare('SELECT * FROM ' . $table . ' WHERE uuid = :uuid AND deleted_at IS NULL LIMIT 1');
        $statement->execute(['uuid' => $uuid]);
        $row = $statement->fetch();

        if ($row !== false) {
            return $row;
        }

        http_response_code(404);
        echo view('errors/404', ['path' => '/licensing']);
        exit;
    }

    private function planPayload(Request $request): array
    {
        $name = trim((string) $request->input('name'));

        return [
            'name' => $name,
            'slug' => $this->slug((string) $request->input('slug', $name)),
            'description' => trim((string) $request->input('description')),
            'price' => (float) $request->input('price', 0),
            'billing_period' => (string) $request->input('billing_period', 'monthly'),
            'is_active' => $request->input('is_active') === '1' ? 1 : 0,
        ];
    }

    private function featurePayload(Request $request): array
    {
        $name = trim((string) $request->input('name'));

        return [
            'feature_group_id' => (int) $request->input('feature_group_id', 0),
            'module' => $this->slug((string) $request->input('module', 'core')),
            'name' => $name,
            'slug' => $this->slug((string) $request->input('slug', $name)),
            'description' => trim((string) $request->input('description')),
            'is_active' => $request->input('is_active') === '1' ? 1 : 0,
        ];
    }

    private function licensePayload(Request $request): array
    {
        return [
            'company_id' => (int) $request->input('company_id', 0),
            'plan_id' => (int) $request->input('plan_id', 0),
            'license_key' => strtoupper(trim((string) $request->input('license_key'))),
            'status' => (string) $request->input('status', 'active'),
            'starts_at' => trim((string) $request->input('starts_at', date('Y-m-d'))),
            'expires_at' => trim((string) $request->input('expires_at')),
        ];
    }

    private function validatePlan(array $data): array
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors['name'] = 'El nombre es obligatorio.';
        }

        if ($data['slug'] === '') {
            $errors['slug'] = 'El slug es obligatorio.';
        }

        if (! in_array($data['billing_period'], ['monthly', 'yearly', 'custom'], true)) {
            $errors['billing_period'] = 'Periodo no valido.';
        }

        return $errors;
    }

    private function validateFeature(array $data): array
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors['name'] = 'El nombre es obligatorio.';
        }

        if ($data['slug'] === '') {
            $errors['slug'] = 'El slug es obligatorio.';
        }

        if ($data['module'] === '') {
            $errors['module'] = 'El modulo es obligatorio.';
        }

        return $errors;
    }

    private function validateLicense(array $data): array
    {
        $errors = [];

        if ($data['company_id'] <= 0) {
            $errors['company_id'] = 'Selecciona una empresa.';
        }

        if ($data['plan_id'] <= 0) {
            $errors['plan_id'] = 'Selecciona un plan.';
        }

        if (! in_array($data['status'], ['active', 'inactive', 'suspended', 'expired', 'cancelled'], true)) {
            $errors['status'] = 'Estado no valido.';
        }

        if ($data['starts_at'] === '') {
            $errors['starts_at'] = 'La fecha de inicio es obligatoria.';
        }

        return $errors;
    }

    private function syncPlanFeatures(PDO $db, int $planId, mixed $features): void
    {
        $featureIds = array_map('intval', is_array($features) ? $features : []);
        $db->prepare('UPDATE plan_features SET deleted_at = NOW(), is_enabled = 0 WHERE plan_id = :plan_id')->execute(['plan_id' => $planId]);
        $statement = $db->prepare(
            'INSERT INTO plan_features (uuid, plan_id, feature_id, feature_key, feature_value, is_enabled)
             VALUES (:uuid, :plan_id, :feature_id, :feature_key, "1", 1)
             ON DUPLICATE KEY UPDATE feature_id = VALUES(feature_id), is_enabled = 1, deleted_at = NULL'
        );

        foreach ($featureIds as $featureId) {
            $feature = $this->featureById($db, $featureId);
            if ($feature === null) {
                continue;
            }

            $statement->execute([
                'uuid' => uuid(),
                'plan_id' => $planId,
                'feature_id' => $featureId,
                'feature_key' => $feature['slug'],
            ]);
        }
    }

    private function featureById(PDO $db, int $featureId): ?array
    {
        $statement = $db->prepare('SELECT id, slug FROM features WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $statement->execute(['id' => $featureId]);
        $feature = $statement->fetch();

        return $feature === false ? null : $feature;
    }

    private function planFeatureIds(int $planId): array
    {
        $statement = $this->db()->prepare('SELECT feature_id FROM plan_features WHERE plan_id = :plan_id AND is_enabled = 1 AND deleted_at IS NULL');
        $statement->execute(['plan_id' => $planId]);

        return array_map('intval', array_column($statement->fetchAll(), 'feature_id'));
    }

    private function syncLimits(PDO $db, int $licenseId, Request $request): void
    {
        $limits = [
            'users' => (int) $request->input('limit_users', 0),
            'extensions' => (int) $request->input('limit_extensions', 0),
            'concurrent_calls' => (int) $request->input('limit_concurrent_calls', 0),
        ];
        $statement = $db->prepare(
            'INSERT INTO license_limits (uuid, company_license_id, limit_key, limit_value)
             VALUES (:uuid, :company_license_id, :limit_key, :limit_value)
             ON DUPLICATE KEY UPDATE limit_value = VALUES(limit_value), deleted_at = NULL'
        );

        foreach ($limits as $key => $value) {
            $statement->execute([
                'uuid' => uuid(),
                'company_license_id' => $licenseId,
                'limit_key' => $key,
                'limit_value' => max(0, $value),
            ]);
        }
    }

    private function licenseLimits(int $licenseId): array
    {
        $statement = $this->db()->prepare('SELECT limit_key, limit_value FROM license_limits WHERE company_license_id = :id AND deleted_at IS NULL');
        $statement->execute(['id' => $licenseId]);
        $limits = [];

        foreach ($statement->fetchAll() as $row) {
            $limits[$row['limit_key']] = (int) $row['limit_value'];
        }

        return $limits;
    }

    private function softDelete(string $table, int $id): void
    {
        $statement = $this->db()->prepare('UPDATE ' . $table . ' SET deleted_at = NOW() WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    private function licenseKey(): string
    {
        return 'UC200-' . strtoupper(bin2hex(random_bytes(6)));
    }

    private function slug(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?: '';

        return trim($slug, '-');
    }

    private function back(string $path, array $errors, array $old): void
    {
        Session::flash('errors', $errors);
        Session::flash('old', $old);
        redirect($path);
    }
}
