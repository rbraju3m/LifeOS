<?php

namespace App\Modules\Shared\Logging;

/**
 * Array keys whose values must never reach logs or the audit log
 * (docs/blueprint/18 I5). Matched case-insensitively; "-" counts as "_".
 */
final class SensitiveKeys
{
    /** Exact key names. */
    private const EXACT = [
        'authorization',
        'cookie',
        'set_cookie',
        'x_xsrf_token',
        'x_csrf_token',
        'secret',
        'code',
        'otp',
        'recovery_code',
        'recovery_codes',
        'api_key',
        'apikey',
        'token',
        'credentials',
    ];

    /** Key patterns: password*, *_password, *_token, *_secret, two_factor_*. */
    private const PATTERN = '/^(password.*|.*_password|.*_token|.*_secret|two_factor_.*)$/';

    public static function isSensitive(int|string $key): bool
    {
        if (is_int($key)) {
            return false;
        }

        $normalised = str_replace('-', '_', mb_strtolower($key));

        return in_array($normalised, self::EXACT, true) || preg_match(self::PATTERN, $normalised) === 1;
    }
}
