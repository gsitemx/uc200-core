<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;

final class DashboardController extends Controller
{
    public function index(Request $request): string
    {
        $db = $this->db();
        $user = auth_user();
        $isSuperAdmin = in_array('super-admin', $user['roles'] ?? [], true);

        return view('dashboard/index', [
            'title' => __('nav.dashboard'),
            'user' => $user,
            'cards' => [
                [
                    'label' => __('modules.companies'),
                    'value' => $isSuperAdmin ? $this->count($db, 'companies') : 1,
                    'hint' => $isSuperAdmin ? 'Clientes registrados' : 'Tenant activo',
                ],
                [
                    'label' => __('menu.users'),
                    'value' => $isSuperAdmin ? $this->count($db, 'users') : $this->countUsersByCompany($db, (int) $user['company_id']),
                    'hint' => 'Cuentas activas e historicas',
                ],
                [
                    'label' => __('menu.modules'),
                    'value' => $this->count($db, 'modules'),
                    'hint' => 'Base preparada para expansion',
                ],
                [
                    'label' => 'Features',
                    'value' => $this->count($db, 'features'),
                    'hint' => 'Capacidades configurables',
                ],
            ],
            'quickActions' => [
                ['label' => __('companies.new'), 'href' => '/companies/create', 'roles' => ['super-admin']],
                ['label' => __('licensing.new_license'), 'href' => '/licensing/licenses/create', 'roles' => ['super-admin']],
                ['label' => __('actions.new_extension'), 'href' => '/pbx/extensions/create', 'roles' => ['super-admin', 'admin-empresa']],
                ['label' => __('menu.recordings'), 'href' => '/pbx/recordings', 'roles' => ['super-admin', 'admin-empresa']],
                ['label' => 'Nuevo telefono', 'href' => '/provisioning/devices/create', 'roles' => ['super-admin', 'admin-empresa']],
            ],
            'modules' => $this->modules($db),
            'flash' => Session::flash('success'),
        ]);
    }

    private function count(\PDO $db, string $table): int
    {
        return (int) $db->query('SELECT COUNT(*) FROM ' . $table . ' WHERE deleted_at IS NULL')->fetchColumn();
    }

    private function modules(\PDO $db): array
    {
        $statement = $db->query(
            'SELECT name, slug, description, version, is_enabled
             FROM modules
             WHERE deleted_at IS NULL
             ORDER BY name'
        );

        return $statement->fetchAll();
    }

    private function countUsersByCompany(\PDO $db, int $companyId): int
    {
        $statement = $db->prepare('SELECT COUNT(*) FROM users WHERE company_id = :company_id AND deleted_at IS NULL');
        $statement->execute(['company_id' => $companyId]);

        return (int) $statement->fetchColumn();
    }
}
