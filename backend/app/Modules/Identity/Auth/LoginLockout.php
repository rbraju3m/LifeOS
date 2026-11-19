<?php

namespace App\Modules\Identity\Auth;

use App\Modules\Identity\Events\AccountLocked;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Notifications\AccountLockedNotification;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Locks an email address for a while after too many failed logins
 * (docs/blueprint/13: 10 failures within an hour → 15 minute lock).
 *
 * Keyed by email whether or not an account exists, so a lock reveals nothing
 * about which addresses are registered. Works alongside the per-minute
 * `login` rate limiter, which slows down rapid guessing.
 */
class LoginLockout
{
    public function __construct(private readonly Cache $cache) {}

    public function secondsRemaining(string $email): int
    {
        $until = $this->cache->get($this->lockKey($email));

        return is_int($until) ? max(0, $until - now()->getTimestamp()) : 0;
    }

    public function recordFailure(string $email, ?User $user): void
    {
        $key = $this->failuresKey($email);
        $window = (int) config('lifeos.auth.lockout_window_minutes') * 60;

        $this->cache->add($key, 0, $window);
        $failures = (int) $this->cache->increment($key);

        if ($failures >= (int) config('lifeos.auth.lockout_failures')) {
            $lockSeconds = (int) config('lifeos.auth.lockout_minutes') * 60;
            $this->cache->put($this->lockKey($email), now()->getTimestamp() + $lockSeconds, $lockSeconds);
            $this->cache->forget($key);
            if ($user !== null) {
                event(new AccountLocked($user));
                $user->notify(new AccountLockedNotification((int) config('lifeos.auth.lockout_minutes')));
            }
        }
    }

    public function clear(string $email): void
    {
        $this->cache->forget($this->failuresKey($email));
    }

    private function failuresKey(string $email): string
    {
        return 'login-failures:'.hash('sha256', $email);
    }

    private function lockKey(string $email): string
    {
        return 'login-locked:'.hash('sha256', $email);
    }
}
