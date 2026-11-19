<?php

use App\Modules\Identity\Http\Middleware\SetLocale;
use App\Modules\Shared\Http\Middleware\AssignRequestId;
use App\Modules\Shared\Http\Problems\ProblemRenderer;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

$isApiRequest = fn (Request $request): bool => $request->is('api/*') || $request->expectsJson();

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);

        // First-party SPA auth: Sanctum starts the session (with CSRF checks)
        // for requests from SANCTUM_STATEFUL_DOMAINS (docs/adr/0007).
        $middleware->statefulApi();
        $middleware->throttleApi();
        $middleware->appendToGroup('api', SetLocale::class);
        // Run before auth so a 401 is also in the right language.
        $middleware->prependToPriorityList(before: AuthenticatesRequests::class, prepend: SetLocale::class);

        // API clients get a 401 problem response, never a redirect to a login page.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions) use ($isApiRequest): void {
        $exceptions->shouldRenderJsonWhen($isApiRequest);
        $exceptions->render(function (Throwable $e, Request $request) use ($isApiRequest) {
            return $isApiRequest($request) ? app(ProblemRenderer::class)->render($e, $request) : null;
        });
    })->create();
