<?php

namespace App\Modules\Shared\Http\Middleware;

use App\Modules\Shared\Http\Problems\ApiProblemException;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * `Idempotency-Key` support for POST create endpoints (docs/blueprint/12 §12.1).
 * Put it on a route after `auth:sanctum`.
 *
 * - Without the header the request runs normally.
 * - Keys are per user: `idem:{user}:{key}` (docs/blueprint/10), kept 24 hours.
 * - A successful (2xx) response is stored with a hash of the request. Repeating
 *   the same request returns the stored response with `Idempotent-Replayed: true`.
 *   The same key with a different request is a 409 `idempotency_key_reused`.
 * - Error responses are not stored, so a client can fix the request and retry
 *   with the same key.
 * - While the first request is still running, a repeat gets a 409
 *   `idempotency_request_in_progress`.
 */
class Idempotency
{
    public const HEADER = 'Idempotency-Key';

    public const REPLAYED_HEADER = 'Idempotent-Replayed';

    public const TTL_SECONDS = 86400;

    private const LOCK_SECONDS = 30;

    /** UUID (any version) or ULID. */
    private const KEY_PATTERN = '/^([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}|[0-9a-hjkmnp-tv-z]{26})$/i';

    /** Response headers worth replaying. */
    private const KEPT_HEADERS = ['Content-Type', 'Location'];

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->headers->get(self::HEADER);
        $user = $request->user();

        if ($key === null || ! $user instanceof Authenticatable) {
            return $next($request);
        }

        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw new ApiProblemException(400, 'idempotency_key_invalid');
        }

        $cacheKey = 'idem:'.$user->getAuthIdentifier().':'.mb_strtolower($key);
        $fingerprint = hash('sha256', $request->method()."\n".$request->path()."\n".$request->getContent());

        $replay = $this->replay($cacheKey, $fingerprint);
        if ($replay !== null) {
            return $replay;
        }

        $lock = Cache::lock($cacheKey.':lock', self::LOCK_SECONDS);
        if (! $lock->get()) {
            throw new ApiProblemException(409, 'idempotency_request_in_progress');
        }

        try {
            // The first request may have finished between the check and the lock.
            $replay = $this->replay($cacheKey, $fingerprint);
            if ($replay !== null) {
                return $replay;
            }

            $response = $next($request);

            if ($response->isSuccessful()) {
                Cache::put($cacheKey, [
                    'fingerprint' => $fingerprint,
                    'status' => $response->getStatusCode(),
                    'headers' => array_filter(array_combine(
                        self::KEPT_HEADERS,
                        array_map(fn (string $h) => $response->headers->get($h), self::KEPT_HEADERS),
                    ), fn ($v) => $v !== null),
                    'content' => (string) $response->getContent(),
                ], self::TTL_SECONDS);
            }

            return $response;
        } finally {
            $lock->release();
        }
    }

    private function replay(string $cacheKey, string $fingerprint): ?Response
    {
        $stored = Cache::get($cacheKey);

        if (! is_array($stored)) {
            return null;
        }

        if (($stored['fingerprint'] ?? null) !== $fingerprint) {
            throw new ApiProblemException(409, 'idempotency_key_reused');
        }

        /** @var array<string, string> $headers */
        $headers = is_array($stored['headers'] ?? null) ? $stored['headers'] : [];

        return new Response(
            is_string($stored['content'] ?? null) ? $stored['content'] : '',
            is_int($stored['status'] ?? null) ? $stored['status'] : 200,
            [...$headers, self::REPLAYED_HEADER => 'true'],
        );
    }
}
