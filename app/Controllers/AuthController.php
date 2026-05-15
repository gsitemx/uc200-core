<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Services\AuthService;

final class AuthController extends Controller
{
    public function showLogin(Request $request): string
    {
        if (Session::get('user_id') !== null) {
            redirect('/dashboard');
        }

        Csrf::regenerate();

        return view('auth/login', [
            'title' => 'Acceso',
            'error' => Session::flash('error'),
        ], 'layouts/guest');
    }

    public function login(Request $request): void
    {
        $credentials = $request->only(['email', 'password']);
        $auth = new AuthService($this->db());

        if (! $auth->attempt((string) $credentials['email'], (string) $credentials['password'])) {
            Session::flash('error', 'Credenciales invalidas o usuario inactivo.');
            redirect('/login');
        }

        Session::flash('success', 'Sesion iniciada correctamente.');
        redirect('/dashboard');
    }

    public function logout(Request $request): void
    {
        Session::destroy();
        redirect('/login');
    }
}
