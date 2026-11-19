<?php

namespace App\Modules\Audit\Listeners;

use App\Modules\Audit\AuditLogger;
use App\Modules\Identity\Events\AccountLocked;
use App\Modules\Identity\Events\LoginFailed;
use App\Modules\Identity\Events\UserRegistered;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\OtherDeviceLogout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Events\Dispatcher;
use Laravel\Fortify\Events\PasswordUpdatedViaController;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;

/**
 * Writes auth.* audit entries for Identity and Fortify events. For these
 * entries the actor is the account the event is about.
 *
 * Laravel's `Failed` event is deliberately not used: Fortify puts the typed
 * password in it and never the user. Identity's LoginFailed is used instead.
 * Each handler picks fields explicitly; event payloads are never copied.
 */
class RecordAuthEvents
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            UserRegistered::class => 'registered',
            Login::class => 'login',
            LoginFailed::class => 'loginFailed',
            AccountLocked::class => 'accountLocked',
            Logout::class => 'logout',
            OtherDeviceLogout::class => 'otherDevicesLoggedOut',
            PasswordReset::class => 'passwordReset',
            PasswordUpdatedViaController::class => 'passwordChanged',
            Verified::class => 'emailVerified',
            TwoFactorAuthenticationConfirmed::class => 'twoFactorEnabled',
            TwoFactorAuthenticationDisabled::class => 'twoFactorDisabled',
            TwoFactorAuthenticationFailed::class => 'twoFactorFailed',
            RecoveryCodesGenerated::class => 'recoveryCodesRegenerated',
            RecoveryCodeReplaced::class => 'recoveryCodeUsed',
        ];
    }

    public function registered(UserRegistered $event): void
    {
        $this->record('auth.registered', $event->user);
    }

    public function login(Login $event): void
    {
        $this->record('auth.login', $event->user, ['via_remember' => $event->remember]);
    }

    public function loginFailed(LoginFailed $event): void
    {
        $this->record('auth.login_failed', $event->user);
    }

    public function accountLocked(AccountLocked $event): void
    {
        $this->record('auth.account_locked', $event->user, [
            'lock_minutes' => (int) config('lifeos.auth.lockout_minutes'),
        ]);
    }

    public function logout(Logout $event): void
    {
        $this->record('auth.logout', $event->user);
    }

    public function otherDevicesLoggedOut(OtherDeviceLogout $event): void
    {
        $this->record('auth.other_sessions_logged_out', $event->user);
    }

    public function passwordReset(PasswordReset $event): void
    {
        $this->record('auth.password_reset', $event->user);
    }

    public function passwordChanged(PasswordUpdatedViaController $event): void
    {
        $this->record('auth.password_changed', $event->user);
    }

    public function emailVerified(Verified $event): void
    {
        $this->record('auth.email_verified', $event->user);
    }

    public function twoFactorEnabled(TwoFactorAuthenticationConfirmed $event): void
    {
        $this->record('auth.two_factor_enabled', $event->user);
    }

    public function twoFactorDisabled(TwoFactorAuthenticationDisabled $event): void
    {
        $this->record('auth.two_factor_disabled', $event->user);
    }

    public function twoFactorFailed(TwoFactorAuthenticationFailed $event): void
    {
        $this->record('auth.two_factor_failed', $event->user);
    }

    public function recoveryCodesRegenerated(RecoveryCodesGenerated $event): void
    {
        $this->record('auth.recovery_codes_regenerated', $event->user);
    }

    public function recoveryCodeUsed(RecoveryCodeReplaced $event): void
    {
        $this->record('auth.recovery_code_used', $event->user);
    }

    /**
     * @param  array<string, scalar|null>  $metadata
     */
    private function record(string $action, mixed $user, array $metadata = []): void
    {
        if ($user instanceof User) {
            $this->audit->record($action, $user, metadata: $metadata);
        }
    }
}
