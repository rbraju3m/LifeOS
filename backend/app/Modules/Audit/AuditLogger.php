<?php

namespace App\Modules\Audit;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Identity\Models\User;
use App\Modules\Shared\Logging\SensitiveKeys;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * The only way to write audit_logs (docs/blueprint/18 R1). Other modules
 * call record() for security- and privacy-relevant actions.
 *
 * - Synchronous, so the entry commits or rolls back with the caller's transaction.
 * - IP and user agent come from the current HTTP request, if there is one.
 * - Metadata must never hold secrets or content: sensitive keys are refused.
 */
class AuditLogger
{
    private const ACTION_PATTERN = '/^[a-z][a-z_]*\.[a-z][a-z_]*$/';

    /**
     * @param  array<string, scalar|null>  $metadata
     */
    public function record(
        string $action,
        ?User $actor,
        ?string $spaceId = null,
        ?string $subjectType = null,
        ?string $subjectId = null,
        array $metadata = [],
    ): AuditLog {
        if (strlen($action) > 80 || preg_match(self::ACTION_PATTERN, $action) !== 1) {
            throw new InvalidArgumentException("Invalid audit action [{$action}].");
        }

        foreach (array_keys($metadata) as $key) {
            if (SensitiveKeys::isSensitive($key)) {
                throw new InvalidArgumentException("Audit metadata may not contain [{$key}].");
            }
        }

        $request = $this->currentRequest();
        $userAgent = $request?->userAgent();

        $log = new AuditLog;
        $log->forceFill([
            'actor_user_id' => $actor?->getKey(),
            'space_id' => $spaceId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'ip' => $request?->ip(),
            'user_agent' => $userAgent === null || $userAgent === '' ? null : mb_substr($userAgent, 0, 255),
            'metadata' => $metadata === [] ? null : $metadata,
        ])->save();

        return $log;
    }

    private function currentRequest(): ?Request
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return null;
        }

        return app()->bound('request') ? app('request') : null;
    }
}
