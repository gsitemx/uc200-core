<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;

final class TenantMiddleware
{
    public function handle(Request $request, Config $config): void
    {
        if (in_array('super-admin', Session::get('user_roles', []), true)) {
            return;
        }

        $companyId = Session::get('company_id');

        if ($companyId === null) {
            $this->deny('No hay empresa asignada a esta cuenta.');
        }

        $statement = Database::connect($config->get('database'))->prepare(
            'SELECT uuid, name, status FROM companies WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $statement->execute(['id' => (int) $companyId]);
        $company = $statement->fetch();

        if ($company === false || $company['status'] !== 'active') {
            Session::destroy();
            $this->deny('La empresa no esta activa.');
        }

        Session::put('company_uuid', $company['uuid']);
        Session::put('company_name', $company['name']);
        Session::put('company_status', $company['status']);
    }

    private function deny(string $message): void
    {
        http_response_code(403);
        echo view('errors/403', ['title' => 'Tenant restringido', 'message' => $message]);
        exit;
    }
}
