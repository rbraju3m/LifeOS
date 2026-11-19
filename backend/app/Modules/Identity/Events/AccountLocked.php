<?php

namespace App\Modules\Identity\Events;

use App\Modules\Identity\Models\User;

/**
 * Too many failed logins locked an existing account's email for a while.
 */
final readonly class AccountLocked
{
    public function __construct(public User $user) {}
}
