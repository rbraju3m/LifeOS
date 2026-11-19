<?php

namespace App\Modules\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every request an ID that is added to all log entries, echoed in the
 * X-Request-Id response header and included in error bodies.
 *
 * An incoming ID (e.g. set by Nginx or Cloudflare) is reused only if it is
 * short and uses a safe character set, so clients cannot inject log content.
 */
class AssignRequestId
{
    public const HEADER = 'X-Request-Id';

    public const ATTRIBUTE = 'request_id';

    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->headers->get(self::HEADER);
        $id = is_string($incoming) && preg_match('/^[A-Za-z0-9._-]{8,64}$/', $incoming) === 1
            ? $incoming
            : (string) Str::ulid();

        $request->attributes->set(self::ATTRIBUTE, $id);
        Context::add(self::ATTRIBUTE, $id);

        $response = $next($request);
        $response->headers->set(self::HEADER, $id);

        return $response;
    }

    public static function idFor(Request $request): ?string
    {
        $id = $request->attributes->get(self::ATTRIBUTE);

        return is_string($id) ? $id : null;
    }
}
