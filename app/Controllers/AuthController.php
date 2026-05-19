<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Services\AuthService;
use PDOException;

final class AuthController extends Controller
{
    public function showLogin(Request $request): string
    {
        if (Session::get('user_id') !== null) {
            redirect('/dashboard');
        }

        Csrf::regenerate();

        return view('auth/login', [
            'title' => __('auth.access'),
            'error' => Session::flash('error'),
        ], 'layouts/guest');
    }

    public function login(Request $request): void
    {
        $credentials = $request->only(['identifier', 'email', 'password']);
        $auth = new AuthService($this->db());
        $identifier = (string) ($credentials['identifier'] ?? $credentials['email'] ?? '');

        if (! $auth->attempt($identifier, (string) $credentials['password'])) {
            Session::flash('error', __('auth.invalid'));
            redirect('/login');
        }

        Session::flash('success', __('auth.success'));
        redirect('/dashboard');
    }

    public function logout(Request $request): void
    {
        Session::destroy();
        redirect('/login');
    }

    public function profile(Request $request): string
    {
        $userId = (int) Session::get('user_id');
        $statement = $this->db()->prepare(
            'SELECT users.*, companies.name AS company_name
             FROM users
             LEFT JOIN companies ON companies.id = users.company_id
             WHERE users.id = :id AND users.deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute(['id' => $userId]);
        $user = $statement->fetch() ?: [];

        $externalAccounts = [];
        try {
            $accounts = $this->db()->prepare(
                'SELECT provider, provider_email, display_name, sync_enabled, linked_at, last_synced_at, status
                 FROM user_external_accounts
                 WHERE user_id = :user_id AND deleted_at IS NULL
                 ORDER BY provider'
            );
            $accounts->execute(['user_id' => $userId]);
            $externalAccounts = $accounts->fetchAll();
        } catch (PDOException) {
            $externalAccounts = [];
        }

        return view('auth/profile', [
            'title' => 'Mi perfil',
            'user' => $user,
            'externalAccounts' => $externalAccounts,
        ]);
    }

    public function updateLanguage(Request $request): void
    {
        $locale = strtolower(substr((string) $request->input('locale', 'es'), 0, 2));
        $locale = in_array($locale, ['es', 'en'], true) ? $locale : 'es';
        $userId = (int) Session::get('user_id');

        if ($userId > 0) {
            try {
                $statement = $this->db()->prepare('UPDATE users SET locale = :locale WHERE id = :id');
                $statement->execute(['locale' => $locale, 'id' => $userId]);
            } catch (PDOException) {
                // The language selector should not break sessions while the locale migration is pending.
            }

            Session::put('user_locale', $locale);
        }

        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '/dashboard');
        $path = parse_url($referer, PHP_URL_PATH) ?: '/dashboard';
        $query = parse_url($referer, PHP_URL_QUERY);

        redirect($path . ($query !== null ? '?' . $query : ''));
    }
}
