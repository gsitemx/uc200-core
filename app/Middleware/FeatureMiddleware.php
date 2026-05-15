<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Services\LicenseService;

final class FeatureMiddleware
{
    public function handle(Request $request, Config $config, string $featureSlug): void
    {
        if (in_array('super-admin', Session::get('user_roles', []), true)) {
            return;
        }

        $companyId = Session::get('company_id');

        if ($companyId === null) {
            $this->deny();
        }

        $service = new LicenseService(Database::connect($config->get('database')));

        if ($service->hasFeature((int) $companyId, $featureSlug)) {
            return;
        }

        $this->deny();
    }

    private function deny(): void
    {
        http_response_code(403);
        echo view('errors/403', [
            'title' => 'Feature no disponible',
            'message' => 'La licencia activa no incluye esta funcionalidad.',
        ]);
        exit;
    }
}
