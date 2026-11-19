<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Catch lazy loading, silently discarded attributes and missing
        // attributes during development and tests.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Block migrate:fresh, db:wipe etc. against production.
        DB::prohibitDestructiveCommands($this->app->isProduction());

        // General API limit (docs/blueprint/12 §12.1): 120 requests/minute per user, or per IP for guests.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by(
            (string) ($request->user()?->getAuthIdentifier() ?? $request->ip()),
        ));
    }
}
