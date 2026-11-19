<?php

namespace App\Modules\Audit;

/**
 * Security events are the `auth.*` actions: users see them at
 * /me/security-events and they are kept 2 years. Everything else
 * (record.secret_revealed, export.requested, …) is kept 1 year.
 */
final class AuditCategory
{
    public const SECURITY_PREFIX = 'auth.';

    public static function isSecurity(string $action): bool
    {
        return str_starts_with($action, self::SECURITY_PREFIX);
    }
}
