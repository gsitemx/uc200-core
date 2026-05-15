<?php

declare(strict_types=1);

namespace App\Core;

final class ModuleLoader
{
    public static function loadRoutes(App $app, string $modulesPath): void
    {
        if (! is_dir($modulesPath)) {
            return;
        }

        foreach (glob($modulesPath . '/*/routes.php') ?: [] as $routesFile) {
            require $routesFile;
        }
    }
}
