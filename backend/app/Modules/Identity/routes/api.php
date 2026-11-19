<?php

use App\Modules\Identity\Http\Controllers\ForgotPasswordController;
use App\Modules\Identity\Http\Controllers\LogoutOtherSessionsController;
use App\Modules\Identity\Http\Controllers\MeController;
use App\Modules\Identity\Http\Controllers\RegisterController;
use App\Modules\Identity\Http\Middleware\RequireRecentPassword;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\ConfirmablePasswordController;
use Laravel\Fortify\Http\Controllers\ConfirmedPasswordStatusController;
use Laravel\Fortify\Http\Controllers\ConfirmedTwoFactorAuthenticationController;
use Laravel\Fortify\Http\Controllers\EmailVerificationNotificationController;
use Laravel\Fortify\Http\Controllers\NewPasswordController;
use Laravel\Fortify\Http\Controllers\PasswordController;
use Laravel\Fortify\Http\Controllers\RecoveryCodeController;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticationController;
use Laravel\Fortify\Http\Controllers\TwoFactorQrCodeController;
use Laravel\Fortify\Http\Controllers\TwoFactorSecretKeyController;
use Laravel\Fortify\Http\Controllers\VerifyEmailController;

/*
| Mounted under /api/v1 by IdentityServiceProvider (docs/blueprint/12 §12.2–12.3).
| Fortify's own route file is disabled; only the controllers listed here are exposed.
*/

Route::prefix('auth')->group(function () {
    Route::post('register', RegisterController::class)->middleware('throttle:register')->name('register');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login')->name('login.store');
    Route::post('two-factor-challenge', [TwoFactorAuthenticatedSessionController::class, 'store'])->middleware('throttle:two-factor')->name('two-factor.login.store');
    Route::post('forgot-password', ForgotPasswordController::class)->middleware('throttle:password-reset')->name('password.email');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:password-reset')->name('password.update');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

        Route::get('email/verify/{id}/{hash}', VerifyEmailController::class)->middleware(['signed', 'throttle:verification'])->name('verification.verify');
        Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])->middleware('throttle:verification')->name('verification.send');

        Route::post('user/confirm-password', [ConfirmablePasswordController::class, 'store'])->middleware('throttle:login')->name('password.confirm.store');
        Route::get('user/confirmed-password-status', [ConfirmedPasswordStatusController::class, 'show'])->name('password.confirmation');
        Route::put('user/password', [PasswordController::class, 'update'])->middleware('throttle:login')->name('user-password.update');
        Route::post('sessions/logout-others', LogoutOtherSessionsController::class)->middleware('throttle:login')->name('sessions.logout-others');

        Route::middleware(RequireRecentPassword::class)->group(function () {
            Route::post('user/two-factor-authentication', [TwoFactorAuthenticationController::class, 'store'])->name('two-factor.enable');
            Route::post('user/confirmed-two-factor-authentication', [ConfirmedTwoFactorAuthenticationController::class, 'store'])->middleware('throttle:two-factor')->name('two-factor.confirm');
            Route::delete('user/two-factor-authentication', [TwoFactorAuthenticationController::class, 'destroy'])->name('two-factor.disable');
            Route::get('user/two-factor-qr-code', [TwoFactorQrCodeController::class, 'show'])->name('two-factor.qr-code');
            Route::get('user/two-factor-secret-key', [TwoFactorSecretKeyController::class, 'show'])->name('two-factor.secret-key');
            Route::get('user/two-factor-recovery-codes', [RecoveryCodeController::class, 'index'])->name('two-factor.recovery-codes');
            Route::post('user/two-factor-recovery-codes', [RecoveryCodeController::class, 'store'])->name('two-factor.regenerate-recovery-codes');
        });
    });
});

Route::middleware('auth:sanctum')->prefix('me')->group(function () {
    Route::get('/', [MeController::class, 'show'])->name('me.show');
    Route::patch('/', [MeController::class, 'update'])->name('me.update');
    Route::patch('preferences', [MeController::class, 'updatePreferences'])->name('me.preferences.update');
});
