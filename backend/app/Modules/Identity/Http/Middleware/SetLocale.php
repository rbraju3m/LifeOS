<?php

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Responds in the signed-in user's language, otherwise the browser's
 * Accept-Language (bn or en), otherwise the app default.
 */
class SetLocale
{
    public const SUPPORTED = ['bn', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $locale = $user instanceof User && in_array($user->locale, self::SUPPORTED, true)
            ? $user->locale
            : ($request->hasHeader('Accept-Language') ? $request->getPreferredLanguage(self::SUPPORTED) : null);

        if (is_string($locale)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
