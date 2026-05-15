<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;

final class HomeController extends Controller
{
    public function index(Request $request): string
    {
        if (Session::get('user_id') === null) {
            redirect('/login');
        }

        redirect('/dashboard');
    }
}
