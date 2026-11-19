<?php

namespace App\Modules\Shared\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;

/**
 * Base provider for every module in app/Modules/<Module> (docs/blueprint/09 §9.2).
 *
 * Put the provider in the module root (e.g. app/Modules/Tasks/TasksServiceProvider.php)
 * and register it in bootstrap/providers.php. It then loads, if present:
 *   - Database/Migrations/
 *   - routes/api.php, mounted under /api/v1 with the `api` middleware group
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    public const API_PREFIX = 'api/v1';

    public function boot(): void
    {
        $dir = $this->moduleDirectory();

        if (is_dir($dir.'/Database/Migrations')) {
            $this->loadMigrationsFrom($dir.'/Database/Migrations');
        }

        if (is_file($dir.'/routes/api.php') && ! $this->app->routesAreCached()) {
            Route::prefix(self::API_PREFIX)
                ->middleware('api')
                ->group($dir.'/routes/api.php');
        }
    }

    protected function moduleDirectory(): string
    {
        $file = (new ReflectionClass($this))->getFileName();

        return dirname($file === false ? __FILE__ : $file);
    }
}
