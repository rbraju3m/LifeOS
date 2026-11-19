<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Events\UserRegistered;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Notifications\RegistrationAttemptNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Creates an account with default preferences and the accepted legal
 * documents, lets other modules set the account up (UserRegistered, same
 * transaction: e.g. the personal space), then sends the verification email.
 *
 * If the email is already registered, nothing is created and the existing
 * owner gets an email instead; the caller returns the same response either
 * way (docs/blueprint/18 I6: no account enumeration).
 */
class RegisterUser
{
    /**
     * @param  array{name: string, email: string, password: string, locale?: string|null, timezone?: string|null}  $input
     */
    public function handle(array $input, ?string $ip): void
    {
        $email = mb_strtolower(trim($input['email']));
        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            $this->notifyExisting($existing, $input['password']);

            return;
        }

        try {
            $user = DB::transaction(function () use ($input, $email, $ip): User {
                $user = User::create([
                    'name' => trim($input['name']),
                    'email' => $email,
                    'password' => $input['password'],
                    'locale' => $input['locale'] ?? 'bn',
                    'timezone' => $input['timezone'] ?? 'Asia/Dhaka',
                ]);

                $user->preferences()->create();

                $packedIp = $ip !== null ? inet_pton($ip) : false;
                foreach (['terms' => 'terms_version', 'privacy' => 'privacy_version'] as $document => $versionKey) {
                    $user->consents()->create([
                        'document' => $document,
                        'version' => (string) config("lifeos.legal.{$versionKey}"),
                        'granted' => true,
                        'ip' => $packedIp === false ? null : $packedIp,
                    ]);
                }

                event(new UserRegistered($user));

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            // Lost a race with a concurrent registration for the same email.
            $winner = User::query()->where('email', $email)->first();
            if ($winner !== null) {
                $winner->notify(new RegistrationAttemptNotification);
            }

            return;
        }

        event(new Registered($user));
    }

    private function notifyExisting(User $user, string $password): void
    {
        // Spend the same hashing time as a real registration.
        Hash::make($password);
        $user->notify(new RegistrationAttemptNotification);
    }
}
