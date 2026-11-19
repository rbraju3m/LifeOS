<?php

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Shared\Http\Problems\ApiProblemException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Step-up check for sensitive actions: the password must have been confirmed
 * (POST /auth/user/confirm-password) within auth.password_timeout seconds.
 * Replaces Laravel's `password.confirm`, which answers with a redirect or a
 * non-standard JSON body.
 */
class RequireRecentPassword
{
    public function handle(Request $request, Closure $next): Response
    {
        $confirmedAt = (int) $request->session()->get('auth.password_confirmed_at', 0);

        if (now()->getTimestamp() - $confirmedAt > (int) config('auth.password_timeout', 900)) {
            throw new ApiProblemException(423, 'password_confirmation_required');
        }

        return $next($request);
    }
}
