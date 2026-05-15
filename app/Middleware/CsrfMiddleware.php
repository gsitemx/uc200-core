<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;

final class CsrfMiddleware
{
    public function handle(Request $request): void
    {
        if (! Csrf::validate((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'La sesion expiro. Intenta de nuevo.');
            redirect('/login');
        }
    }
}
