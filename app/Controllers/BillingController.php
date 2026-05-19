<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuditService;
use App\Services\BillingService;
use App\Services\Payments\PaymentGatewayManager;
use App\Support\ApiResponse;

final class BillingController extends Controller
{
    public function dashboard(Request $request): string
    {
        $resellerId = $this->currentResellerId();
        $service = new BillingService($this->db());
        $suspended = has_role('super-admin') ? $service->suspendExpiredTenants() : 0;

        if ($suspended > 0) {
            Session::flash('success', $suspended . ' tenant(s) suspendidos por vencimiento.');
        }

        return view('billing/dashboard', [
            'title' => 'Billing Engine',
            'flash' => Session::flash('success'),
            'metrics' => $service->metrics($resellerId),
            'subscriptions' => $this->subscriptionRows(),
            'invoices' => $this->invoiceRows(),
        ]);
    }

    public function resellers(Request $request): string
    {
        return view('billing/resellers', [
            'title' => 'Resellers',
            'resellers' => $this->resellerRows(),
            'companies' => $this->companies(),
            'flash' => Session::flash('success'),
        ]);
    }

    public function storeReseller(Request $request): void
    {
        $data = [
            'parent_reseller_id' => (int) $request->input('parent_reseller_id', 0) ?: null,
            'company_id' => (int) $request->input('company_id', 0) ?: null,
            'name' => trim((string) $request->input('name')),
            'slug' => strtolower(trim((string) $request->input('slug'))),
            'custom_domain' => trim((string) $request->input('custom_domain')) ?: null,
            'branding_json' => (string) $request->input('branding_json', '{}'),
            'limits_json' => (string) $request->input('limits_json', '{}'),
            'status' => (string) $request->input('status', 'active'),
        ];

        $this->db()->prepare(
            'INSERT INTO resellers (uuid, parent_reseller_id, company_id, name, slug, custom_domain, branding_json, limits_json, status)
             VALUES (:uuid, :parent_reseller_id, :company_id, :name, :slug, :custom_domain, :branding_json, :limits_json, :status)'
        )->execute(['uuid' => uuid()] + $data);

        Session::flash('success', 'Reseller creado correctamente.');
        redirect('/billing/resellers');
    }

    public function tenants(Request $request): string
    {
        return view('billing/tenants', [
            'title' => 'Tenant Management',
            'tenants' => $this->tenantRows(),
            'resellers' => $this->resellerRows(),
            'flash' => Session::flash('success'),
        ]);
    }

    public function assignTenant(Request $request): void
    {
        $companyId = (int) $request->input('company_id', 0);
        $currentResellerId = $this->currentResellerId();
        $resellerId = ($currentResellerId !== null && $currentResellerId > 0) ? $currentResellerId : (int) $request->input('reseller_id', 0);

        if ($companyId > 0 && $resellerId > 0) {
            $this->db()->prepare(
                'INSERT INTO reseller_companies (uuid, reseller_id, company_id, status)
                 VALUES (:uuid, :reseller_id, :company_id, "active")
                 ON DUPLICATE KEY UPDATE reseller_id = VALUES(reseller_id), status = "active", deleted_at = NULL'
            )->execute(['uuid' => uuid(), 'reseller_id' => $resellerId, 'company_id' => $companyId]);
        }

        Session::flash('success', 'Tenant asignado a reseller.');
        redirect('/billing/tenants');
    }

    public function suspendTenant(Request $request): void
    {
        $this->setTenantStatus($request, 'suspended');
    }

    public function reactivateTenant(Request $request): void
    {
        $this->setTenantStatus($request, 'active');
    }

    public function subscriptions(Request $request): string
    {
        return view('billing/subscriptions', [
            'title' => 'Subscriptions',
            'subscriptions' => $this->subscriptionRows(),
            'companies' => $this->companies(),
            'plans' => $this->plans(),
            'resellers' => $this->resellerRows(),
            'flash' => Session::flash('success'),
        ]);
    }

    public function storeSubscription(Request $request): void
    {
        $currentResellerId = $this->currentResellerId();
        $data = [
            'reseller_id' => ($currentResellerId !== null && $currentResellerId > 0) ? $currentResellerId : ((int) $request->input('reseller_id', 0) ?: null),
            'company_id' => (int) $request->input('company_id', 0),
            'plan_id' => (int) $request->input('plan_id', 0),
            'billing_period' => (string) $request->input('billing_period', 'monthly'),
            'amount' => (float) $request->input('amount', 0),
            'currency' => strtoupper(substr((string) $request->input('currency', 'USD'), 0, 3)),
            'current_period_start' => (string) $request->input('current_period_start', date('Y-m-d')),
            'current_period_end' => (string) $request->input('current_period_end', date('Y-m-d', strtotime('+1 month'))),
            'grace_until' => (string) $request->input('grace_until', date('Y-m-d', strtotime('+7 days'))),
            'auto_renew' => (string) $request->input('auto_renew', 'yes'),
            'status' => (string) $request->input('status', 'active'),
        ];
        $this->db()->prepare(
            'INSERT INTO billing_subscriptions
             (uuid, reseller_id, company_id, plan_id, billing_period, amount, currency, current_period_start, current_period_end, grace_until, auto_renew, status)
             VALUES (:uuid, :reseller_id, :company_id, :plan_id, :billing_period, :amount, :currency, :current_period_start, :current_period_end, :grace_until, :auto_renew, :status)'
        )->execute(['uuid' => uuid()] + $data);

        Session::flash('success', 'Suscripcion creada correctamente.');
        redirect('/billing/subscriptions');
    }

    public function invoices(Request $request): string
    {
        return view('billing/invoices', [
            'title' => 'Invoices',
            'invoices' => $this->invoiceRows(),
            'flash' => Session::flash('success'),
        ]);
    }

    public function usage(Request $request): string
    {
        return view('billing/usage', [
            'title' => 'Usage Tracking',
            'usage' => $this->usageRows(),
            'usageMetrics' => $this->usageMetrics(),
            'flash' => Session::flash('success'),
        ]);
    }

    public function webhook(Request $request): Response
    {
        $provider = strtolower((string) $request->input('provider', 'stripe'));
        try {
            $result = (new PaymentGatewayManager())->gateway($provider)->handleWebhook($request->json(), []);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error('unsupported_gateway', $exception->getMessage(), 400);
        }

        return ApiResponse::success($result);
    }

    private function setTenantStatus(Request $request, string $status): void
    {
        $companyId = (int) $request->input('id', 0);
        if (! $this->canManageCompany($companyId)) {
            http_response_code(403);
            exit('Forbidden');
        }

        $this->db()->prepare('UPDATE companies SET status = :status WHERE id = :id')->execute(['status' => $status, 'id' => $companyId]);
        (new AuditService($this->db()))->record('billing.tenant.' . $status, 'companies', $companyId, [], $companyId);

        Session::flash('success', $status === 'active' ? 'Tenant reactivado.' : 'Tenant suspendido.');
        redirect('/billing/tenants');
    }

    private function resellerRows(): array
    {
        $sql = 'SELECT r.*, c.name AS company_name, pr.name AS parent_name FROM resellers r LEFT JOIN companies c ON c.id = r.company_id LEFT JOIN resellers pr ON pr.id = r.parent_reseller_id WHERE r.deleted_at IS NULL';
        $params = [];
        $resellerId = $this->currentResellerId();
        if ($resellerId !== null) {
            $sql .= ' AND r.id = :reseller_id';
            $params['reseller_id'] = $resellerId;
        }
        $sql .= ' ORDER BY r.name';
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function tenantRows(): array
    {
        $sql = 'SELECT c.*, r.name AS reseller_name FROM companies c LEFT JOIN reseller_companies rc ON rc.company_id = c.id AND rc.deleted_at IS NULL LEFT JOIN resellers r ON r.id = rc.reseller_id WHERE c.deleted_at IS NULL';
        $params = [];
        $resellerId = $this->currentResellerId();
        if ($resellerId !== null) {
            $sql .= ' AND rc.reseller_id = :reseller_id';
            $params['reseller_id'] = $resellerId;
        }
        $sql .= ' ORDER BY c.name';
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function subscriptionRows(): array
    {
        $sql = 'SELECT s.*, c.name AS company_name, p.name AS plan_name, r.name AS reseller_name
                FROM billing_subscriptions s
                INNER JOIN companies c ON c.id = s.company_id
                INNER JOIN plans p ON p.id = s.plan_id
                LEFT JOIN resellers r ON r.id = s.reseller_id
                WHERE s.deleted_at IS NULL';
        $params = [];
        $resellerId = $this->currentResellerId();
        if ($resellerId !== null) {
            $sql .= ' AND s.reseller_id = :reseller_id';
            $params['reseller_id'] = $resellerId;
        }
        $sql .= ' ORDER BY s.created_at DESC LIMIT 50';
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function invoiceRows(): array
    {
        $sql = 'SELECT i.*, c.name AS company_name, r.name AS reseller_name
                FROM billing_invoices i
                INNER JOIN companies c ON c.id = i.company_id
                LEFT JOIN resellers r ON r.id = i.reseller_id
                WHERE i.deleted_at IS NULL';
        $params = [];
        $resellerId = $this->currentResellerId();
        if ($resellerId !== null) {
            $sql .= ' AND i.reseller_id = :reseller_id';
            $params['reseller_id'] = $resellerId;
        }
        $sql .= ' ORDER BY i.issue_date DESC, i.id DESC LIMIT 50';
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function usageRows(): array
    {
        $sql = 'SELECT u.*, c.name AS company_name, r.name AS reseller_name
                FROM billing_usage_records u
                INNER JOIN companies c ON c.id = u.company_id
                LEFT JOIN resellers r ON r.id = u.reseller_id
                WHERE u.deleted_at IS NULL';
        $params = [];
        $resellerId = $this->currentResellerId();
        if ($resellerId !== null) {
            $sql .= ' AND u.reseller_id = :reseller_id';
            $params['reseller_id'] = $resellerId;
        }
        $sql .= ' ORDER BY u.period_end DESC, u.created_at DESC LIMIT 100';
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function usageMetrics(): array
    {
        $sql = 'SELECT metric, SUM(quantity) AS total
                FROM billing_usage_records
                WHERE deleted_at IS NULL';
        $params = [];
        $resellerId = $this->currentResellerId();
        if ($resellerId !== null) {
            $sql .= ' AND reseller_id = :reseller_id';
            $params['reseller_id'] = $resellerId;
        }
        $sql .= ' GROUP BY metric ORDER BY total DESC LIMIT 8';
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function companies(): array
    {
        $sql = 'SELECT c.id, c.name, c.status
                FROM companies c
                LEFT JOIN reseller_companies rc ON rc.company_id = c.id AND rc.deleted_at IS NULL
                WHERE c.deleted_at IS NULL';
        $params = [];
        $resellerId = $this->currentResellerId();
        if ($resellerId !== null) {
            $sql .= ' AND rc.reseller_id = :reseller_id';
            $params['reseller_id'] = $resellerId;
        }
        $sql .= ' ORDER BY c.name';
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function plans(): array
    {
        return $this->db()->query('SELECT id, name, price, billing_period FROM plans WHERE deleted_at IS NULL ORDER BY name')->fetchAll();
    }

    private function currentResellerId(): ?int
    {
        if (has_role('super-admin')) {
            return null;
        }

        $sessionResellerId = (int) Session::get('reseller_id', 0);
        if ($sessionResellerId > 0) {
            return $sessionResellerId;
        }

        $companyId = Session::get('company_id');
        if ($companyId === null) {
            return 0;
        }

        $statement = $this->db()->prepare(
            'SELECT id FROM resellers WHERE company_id = :company_id AND deleted_at IS NULL LIMIT 1'
        );
        $statement->execute(['company_id' => (int) $companyId]);
        $resellerId = (int) $statement->fetchColumn();

        return $resellerId > 0 ? $resellerId : 0;
    }

    private function canManageCompany(int $companyId): bool
    {
        if ($companyId <= 0) {
            return false;
        }

        if (has_role('super-admin')) {
            return true;
        }

        $resellerId = $this->currentResellerId();
        if ($resellerId === null || $resellerId <= 0) {
            return false;
        }

        $statement = $this->db()->prepare(
            'SELECT COUNT(*) FROM reseller_companies WHERE company_id = :company_id AND reseller_id = :reseller_id AND deleted_at IS NULL'
        );
        $statement->execute(['company_id' => $companyId, 'reseller_id' => $resellerId]);

        return (int) $statement->fetchColumn() > 0;
    }
}
