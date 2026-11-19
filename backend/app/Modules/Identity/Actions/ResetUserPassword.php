<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

/**
 * Used by Fortify's reset-password endpoint. Rotating the remember token and
 * the password hash ends every other session and "remember me" cookie.
 */
class ResetUserPassword implements ResetsUserPasswords
{
    /**
     * @param  User  $user
     * @param  array<string, mixed>  $input
     */
    public function reset($user, array $input): void
    {
        $validated = Validator::make($input, [
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ])->validate();

        $user->forceFill([
            'password' => $validated['password'],
            'remember_token' => Str::random(60),
        ])->save();
    }
}
