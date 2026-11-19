<?php

namespace App\Modules\Identity\Events;

use App\Modules\Identity\Models\User;

/**
 * A wrong password was given for an existing account. Not dispatched for
 * unknown emails: there is no account to attach the attempt to.
 */
final readonly class LoginFailed
{
    public function __construct(public User $user) {}
}
