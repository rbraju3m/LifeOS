<?php

namespace App\Modules\Identity\Listeners;

use App\Modules\Identity\Models\User;
use Illuminate\Auth\Events\Login;

class RecordLastLogin
{
    public function handle(Login $event): void
    {
        if ($event->user instanceof User) {
            // Query-builder update so updated_at (a content-change marker) is untouched.
            User::query()->whereKey($event->user->getAuthIdentifier())->toBase()->update(['last_login_at' => now()]);
        }
    }
}
