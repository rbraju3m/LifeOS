<?php

namespace App\Modules\Identity\Events;

use App\Modules\Identity\Models\User;

/**
 * Dispatched inside the registration transaction, so listeners (e.g. Spaces
 * creating the personal space) commit or roll back together with the user.
 * Listeners must therefore be synchronous and must not send email.
 */
final readonly class UserRegistered
{
    public function __construct(public User $user) {}
}
