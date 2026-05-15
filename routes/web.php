<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\CompanyController;
use App\Controllers\DashboardController;
use App\Controllers\HomeController;
use App\Controllers\LicensingController;
use App\Controllers\PbxController;

$router = $app->router();

$router->get('/', [HomeController::class, 'index']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout'], ['auth']);
$router->get('/dashboard', [DashboardController::class, 'index'], ['auth', 'tenant']);

$router->get('/companies', [CompanyController::class, 'index'], ['auth', 'role:super-admin']);
$router->get('/companies/create', [CompanyController::class, 'create'], ['auth', 'role:super-admin']);
$router->post('/companies/store', [CompanyController::class, 'store'], ['auth', 'role:super-admin']);
$router->get('/companies/show', [CompanyController::class, 'show'], ['auth', 'role:super-admin']);
$router->get('/companies/edit', [CompanyController::class, 'edit'], ['auth', 'role:super-admin']);
$router->post('/companies/update', [CompanyController::class, 'update'], ['auth', 'role:super-admin']);
$router->post('/companies/delete', [CompanyController::class, 'destroy'], ['auth', 'role:super-admin']);
$router->get('/companies/dashboard', [CompanyController::class, 'dashboard'], ['auth', 'tenant', 'role:super-admin,admin-empresa']);

$router->get('/licensing', [LicensingController::class, 'dashboard'], ['auth', 'role:super-admin']);
$router->get('/licensing/plans', [LicensingController::class, 'plans'], ['auth', 'role:super-admin']);
$router->get('/licensing/plans/create', [LicensingController::class, 'createPlan'], ['auth', 'role:super-admin']);
$router->post('/licensing/plans/store', [LicensingController::class, 'storePlan'], ['auth', 'role:super-admin']);
$router->get('/licensing/plans/edit', [LicensingController::class, 'editPlan'], ['auth', 'role:super-admin']);
$router->post('/licensing/plans/update', [LicensingController::class, 'updatePlan'], ['auth', 'role:super-admin']);
$router->post('/licensing/plans/delete', [LicensingController::class, 'deletePlan'], ['auth', 'role:super-admin']);
$router->get('/licensing/features', [LicensingController::class, 'features'], ['auth', 'role:super-admin']);
$router->get('/licensing/features/create', [LicensingController::class, 'createFeature'], ['auth', 'role:super-admin']);
$router->post('/licensing/features/store', [LicensingController::class, 'storeFeature'], ['auth', 'role:super-admin']);
$router->get('/licensing/features/edit', [LicensingController::class, 'editFeature'], ['auth', 'role:super-admin']);
$router->post('/licensing/features/update', [LicensingController::class, 'updateFeature'], ['auth', 'role:super-admin']);
$router->post('/licensing/features/delete', [LicensingController::class, 'deleteFeature'], ['auth', 'role:super-admin']);
$router->get('/licensing/licenses', [LicensingController::class, 'licenses'], ['auth', 'role:super-admin']);
$router->get('/licensing/licenses/create', [LicensingController::class, 'createLicense'], ['auth', 'role:super-admin']);
$router->post('/licensing/licenses/store', [LicensingController::class, 'storeLicense'], ['auth', 'role:super-admin']);
$router->get('/licensing/licenses/edit', [LicensingController::class, 'editLicense'], ['auth', 'role:super-admin']);
$router->post('/licensing/licenses/update', [LicensingController::class, 'updateLicense'], ['auth', 'role:super-admin']);
$router->post('/licensing/licenses/delete', [LicensingController::class, 'deleteLicense'], ['auth', 'role:super-admin']);

$router->get('/pbx', [PbxController::class, 'dashboard'], ['auth', 'tenant', 'role:super-admin,admin-empresa']);
$router->get('/pbx/extensions', [PbxController::class, 'extensions'], ['auth', 'tenant', 'role:super-admin,admin-empresa']);
$router->get('/pbx/extensions/create', [PbxController::class, 'createExtension'], ['auth', 'tenant', 'role:super-admin,admin-empresa']);
$router->post('/pbx/extensions/store', [PbxController::class, 'storeExtension'], ['auth', 'tenant', 'role:super-admin,admin-empresa']);
$router->get('/pbx/extensions/edit', [PbxController::class, 'editExtension'], ['auth', 'tenant', 'role:super-admin,admin-empresa']);
$router->post('/pbx/extensions/update', [PbxController::class, 'updateExtension'], ['auth', 'tenant', 'role:super-admin,admin-empresa']);
$router->post('/pbx/extensions/delete', [PbxController::class, 'deleteExtension'], ['auth', 'tenant', 'role:super-admin,admin-empresa']);
$router->get('/pbx/transports', [PbxController::class, 'transports'], ['auth', 'role:super-admin']);
$router->get('/pbx/transports/create', [PbxController::class, 'createTransport'], ['auth', 'role:super-admin']);
$router->post('/pbx/transports/store', [PbxController::class, 'storeTransport'], ['auth', 'role:super-admin']);
$router->get('/pbx/transports/edit', [PbxController::class, 'editTransport'], ['auth', 'role:super-admin']);
$router->post('/pbx/transports/update', [PbxController::class, 'updateTransport'], ['auth', 'role:super-admin']);
$router->post('/pbx/transports/delete', [PbxController::class, 'deleteTransport'], ['auth', 'role:super-admin']);
$router->get('/pbx/realtime', [PbxController::class, 'realtime'], ['auth', 'role:super-admin']);
