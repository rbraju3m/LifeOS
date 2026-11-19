<?php

namespace App\Modules\Identity;

use App\Modules\Identity\Actions\ResetUserPassword;
use App\Modules\Identity\Actions\UpdateUserPassword;
use App\Modules\Identity\Auth\AuthenticateUser;
use App\Modules\Identity\Http\Responses\LoginResponse;
use App\Modules\Identity\Listeners\RecordLastLogin;
use App\Modules\Identity\Models\User;
use App\Modules\Shared\Providers\ModuleServiceProvider;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\SessionGuard;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Fortify;

class IdentityServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        // Routes are defined in this module's routes/api.php instead.
        Fortify::ignoreRoutes();

        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        $this->app->singleton(TwoFactorLoginResponseContract::class, LoginResponse::class);
    }

    public function boot(): void
    {
        parent::boot();

        Fortify::authenticateUsing(fn (Request $request) => app(AuthenticateUser::class)($request));
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);

        // NIST-style rules: length + breached-password check, no composition rules
        // (docs/blueprint/13). The breach check sends only a 5-char SHA-1 prefix.
        Password::defaults(fn () => Password::min(10)->max(128)->uncompromised());

        // Reset links open the SPA, which posts the token to /auth/reset-password.
        ResetPassword::createUrlUsing(fn (User $user, string $token): string => config('lifeos.frontend_url')
            .'/reset-password?'.http_build_query(['token' => $token, 'email' => $user->email]));

        Auth::resolved(function ($auth): void {
            $guard = $auth->guard('web');
            if ($guard instanceof SessionGuard) {
                $guard->setRememberDuration((int) config('lifeos.auth.remember_minutes'));
            }
        });

        Event::listen(Login::class, RecordLastLogin::class);

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        $email = fn (Request $request): string => mb_strtolower(trim($request->string('email')->toString()));

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by($email($request).'|'.$request->ip()));

        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by(
            ($request->session()->get('login.id') ?? $request->user()?->getAuthIdentifier() ?? '').'|'.$request->ip(),
        ));

        RateLimiter::for('register', fn (Request $request) => Limit::perHour(10)->by((string) $request->ip()));

        RateLimiter::for('password-reset', fn (Request $request) => [
            Limit::perHour(3)->by('email:'.$email($request)),
            Limit::perHour(20)->by('ip:'.$request->ip()),
        ]);

        RateLimiter::for('verification', fn (Request $request) => Limit::perHour(6)->by(
            (string) ($request->user()?->getAuthIdentifier() ?? $request->ip()),
        ));
    }
}
