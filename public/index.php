<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Config;
use App\Core\Env;
use App\Core\ModuleLoader;
use App\Core\Session;

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

Env::load(BASE_PATH . '/.env');

$config = new Config(require BASE_PATH . '/config/app.php');
Session::start($config->get('session', []));

$app = new App($config);
require BASE_PATH . '/routes/web.php';
ModuleLoader::loadRoutes($app, BASE_PATH . '/modules');

$app->run();
