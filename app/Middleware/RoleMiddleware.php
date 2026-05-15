<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Services\RoleService;

final class RoleMiddleware
{
    public function handle(Request $request, Config $config, array $roles): void
    {
        $userId = Session::get('user_id');

        if ($userId === null) {
            Session::flash('error', 'Inicia sesion para continuar.');
            redirect('/login');
        }

        $service = new RoleService(Database::connect($config->get('database')));

        if ($service->userHasAnyRole((int) $userId, $roles)) {
            return;
        }

        http_response_code(403);
        echo view('errors/403', ['title' => 'Acceso restringido']);
        exit;
    }
}
