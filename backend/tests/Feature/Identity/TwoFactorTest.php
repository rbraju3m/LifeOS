<?php

use App\Modules\Identity\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

function currentOtp(User $user): string
{
    return app(Google2FA::class)->getCurrentOtp(decrypt($user->fresh()->two_factor_secret));
}

/** The code for the next 30-second window (accepted within the ±1 window, and not a replay). */
function nextOtp(User $user): string
{
    $google2fa = app(Google2FA::class);

    return $google2fa->oathTotp(decrypt($user->fresh()->two_factor_secret), $google2fa->getTimestamp() + 1);
}

function enableTwoFactor(User $user): void
{
    test()->actingAs($user)
        ->postJson('/api/v1/auth/user/confirm-password', ['password' => UserFactory::PASSWORD])->assertCreated();
    test()->postJson('/api/v1/auth/user/two-factor-authentication')->assertOk();
    test()->postJson('/api/v1/auth/user/confirmed-two-factor-authentication', ['code' => currentOtp($user)])->assertOk();
    test()->postJson('/api/v1/auth/logout')->assertNoContent();
}

it('requires a recent password confirmation to manage 2FA', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/v1/auth/user/two-factor-authentication')
        ->assertStatus(423)
        ->assertJson(['code' => 'password_confirmation_required']);

    $this->postJson('/api/v1/auth/user/confirm-password', ['password' => UserFactory::PASSWORD])->assertCreated();
    $this->postJson('/api/v1/auth/user/two-factor-authentication')->assertOk();

    $this->travel(16)->minutes();
    $this->getJson('/api/v1/auth/user/two-factor-recovery-codes')->assertStatus(423);
});

it('asks for a code after the password, then signs in with it', function () {
    $user = User::factory()->create();
    enableTwoFactor($user);
    expect($user->fresh()->hasTwoFactorEnabled())->toBeTrue();

    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => UserFactory::PASSWORD])
        ->assertOk()
        ->assertExactJson(['two_factor' => true]);
    $this->assertGuest('web');

    $this->postJson('/api/v1/auth/two-factor-challenge', ['code' => '000000'])->assertUnprocessable();
    // The code used to confirm 2FA cannot be replayed, so use the next one.
    $this->postJson('/api/v1/auth/two-factor-challenge', ['code' => nextOtp($user)])
        ->assertOk()
        ->assertJsonPath('data.two_factor_enabled', true);
    $this->assertAuthenticatedAs($user, 'web');
});

it('accepts each recovery code once', function () {
    $user = User::factory()->create();
    enableTwoFactor($user);
    $code = json_decode(decrypt($user->fresh()->two_factor_recovery_codes), true)[0];

    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => UserFactory::PASSWORD]);
    $this->postJson('/api/v1/auth/two-factor-challenge', ['recovery_code' => $code])->assertOk();
    $this->postJson('/api/v1/auth/logout');

    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => UserFactory::PASSWORD]);
    $this->postJson('/api/v1/auth/two-factor-challenge', ['recovery_code' => $code])->assertUnprocessable();
});
