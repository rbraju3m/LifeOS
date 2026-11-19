<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

/**
 * Used by Fortify's PUT /auth/user/password. Other sessions are signed out
 * because Sanctum's AuthenticateSession middleware compares the stored
 * password hash; it re-stores the new hash for the current session after the
 * request, so only the other sessions are signed out.
 */
class UpdateUserPassword implements UpdatesUserPasswords
{
    /**
     * @param  User  $user
     * @param  array<string, mixed>  $input
     */
    public function update($user, array $input): void
    {
        $validated = Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::defaults()],
        ])->validateWithBag('updatePassword');

        $user->forceFill([
            'password' => $validated['password'],
            'remember_token' => Str::random(60),
        ])->save();
    }
}
