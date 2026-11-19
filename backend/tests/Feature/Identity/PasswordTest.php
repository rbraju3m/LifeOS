<?php

use App\Modules\Identity\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('answers forgot-password the same way for known and unknown emails', function () {
    Notification::fake();
    $user = User::factory()->create();

    $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertStatus(202);
    $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])->assertStatus(202);

    expect($known->json())->toBe($unknown->json());
    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use ($user) {
        $url = call_user_func(ResetPassword::$createUrlCallback, $user, $n->token);

        return str_starts_with($url, config('lifeos.frontend_url').'/reset-password?token=');
    });
});

it('resets the password with a valid token and signs out remembered devices', function () {
    $user = User::factory()->create();
    $oldRememberToken = $user->remember_token;
    $token = app('auth.password.broker')->createToken($user);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'my new long password',
        'password_confirmation' => 'my new long password',
    ])->assertOk();

    $user->refresh();
    expect(Hash::check('my new long password', $user->password))->toBeTrue()
        ->and($user->remember_token)->not->toBe($oldRememberToken);
});

it('rejects an invalid reset token', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => 'not-a-real-token',
        'email' => $user->email,
        'password' => 'my new long password',
        'password_confirmation' => 'my new long password',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('changes the password only with the current one', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->putJson('/api/v1/auth/user/password', [
        'current_password' => 'not my password',
        'password' => 'my new long password',
        'password_confirmation' => 'my new long password',
    ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

    $this->putJson('/api/v1/auth/user/password', [
        'current_password' => UserFactory::PASSWORD,
        'password' => 'my new long password',
        'password_confirmation' => 'my new long password',
    ])->assertOk();

    expect(Hash::check('my new long password', $user->fresh()->password))->toBeTrue();
});

it('logs out other devices only with the current password', function () {
    $user = User::factory()->create();
    $hashBefore = $user->password;

    $this->actingAs($user)->postJson('/api/v1/auth/sessions/logout-others', ['password' => 'wrong'])
        ->assertJsonValidationErrors('password');

    $this->postJson('/api/v1/auth/sessions/logout-others', ['password' => UserFactory::PASSWORD])->assertNoContent();

    // Re-hashing the password invalidates every other session (AuthenticateSession).
    expect($user->fresh()->password)->not->toBe($hashBefore);
});
