<?php

namespace App\Modules\Identity\Auth;

use App\Modules\Identity\Enums\UserStatus;
use App\Modules\Identity\Events\LoginFailed;
use App\Modules\Identity\Models\User;
use App\Modules\Shared\Http\Problems\ApiProblemException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Fortify's credential check (Fortify::authenticateUsing).
 *
 * - Locked emails are refused before any password check (423).
 * - Unknown emails still pay for a hash check, so response timing does not
 *   reveal which addresses have accounts.
 * - Suspended accounts are refused only after a correct password (403).
 * - Fortify may call this twice per login (2FA check, then login); the result
 *   is memoised on the request so failures are counted once.
 */
class AuthenticateUser
{
    private const RESULT = 'lifeos.authenticated_user';

    private static ?string $dummyHash = null;

    public function __construct(private readonly LoginLockout $lockout) {}

    public function __invoke(Request $request): ?User
    {
        if ($request->attributes->has(self::RESULT)) {
            $memo = $request->attributes->get(self::RESULT);

            return $memo instanceof User ? $memo : null;
        }

        $user = $this->attempt($request);
        $request->attributes->set(self::RESULT, $user ?? false);

        return $user;
    }

    private function attempt(Request $request): ?User
    {
        $email = mb_strtolower(trim($request->string('email')->toString()));
        $password = $request->string('password')->toString();

        $locked = $this->lockout->secondsRemaining($email);
        if ($locked > 0) {
            throw new ApiProblemException(423, 'account_locked', null, ['retry_after_seconds' => $locked]);
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            Hash::check($password, self::$dummyHash ??= Hash::make('lifeos-timing-equaliser'));
            $this->lockout->recordFailure($email, null);

            return null;
        }

        if (! Hash::check($password, $user->password)) {
            event(new LoginFailed($user));
            $this->lockout->recordFailure($email, $user);

            return null;
        }

        if ($user->status === UserStatus::Suspended) {
            throw new ApiProblemException(403, 'account_suspended');
        }

        $this->lockout->clear($email);

        if (Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => $password])->save();
        }

        return $user;
    }
}
