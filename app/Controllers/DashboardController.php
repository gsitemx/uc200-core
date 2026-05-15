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
            'title' => 'Dashboard',
            'user' => $user,
            'cards' => [
                [
                    'label' => 'Empresas',
                    'value' => $isSuperAdmin ? $this->count($db, 'companies') : 1,
                    'hint' => $isSuperAdmin ? 'Clientes registrados' : 'Tenant activo',
                ],
                [
                    'label' => 'Usuarios',
                    'value' => $isSuperAdmin ? $this->count($db, 'users') : $this->countUsersByCompany($db, (int) $user['company_id']),
                    'hint' => 'Cuentas activas e historicas',
                ],
                [
                    'label' => 'Modulos',
                    'value' => $this->count($db, 'modules'),
                    'hint' => 'Base preparada para expansion',
                ],
                [
                    'label' => 'Features',
                    'value' => $this->count($db, 'features'),
                    'hint' => 'Capacidades configurables',
                ],
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
