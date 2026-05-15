<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\Request;
use App\Core\Session;

final class AuthMiddleware
{
    public function handle(Request $request, Config $config): void
    {
        if (Session::get('user_id') !== null) {
            return;
        }

        Session::flash('error', 'Inicia sesion para continuar.');
        redirect('/login');
    }
}
