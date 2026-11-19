<?php

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Notifications\AccountLockedNotification;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function login(string $email, string $password = UserFactory::PASSWORD, bool $remember = false)
{
    return test()->postJson('/api/v1/auth/login', compact('email', 'password', 'remember'));
}

it('signs in and returns the user without secrets', function () {
    $user = User::factory()->create();

    $response = login($user->email)->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.preferences.week_start', 6);

    expect($response->getContent())
        ->not->toContain('password')
        ->not->toContain('remember_token')
        ->not->toContain('two_factor_secret');
    $this->assertAuthenticatedAs($user, 'web');
    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('is case-insensitive on email', function () {
    $user = User::factory()->create(['email' => 'tanvir@example.com']);

    login('  Tanvir@Example.COM ')->assertOk()->assertJsonPath('data.id', $user->id);
});

it('gives the same error for a wrong password and an unknown email', function () {
    User::factory()->create(['email' => 'tanvir@example.com']);

    $wrongPassword = login('tanvir@example.com', 'wrong password!!')->assertUnprocessable();
    $unknownEmail = login('nobody@example.com', 'wrong password!!')->assertUnprocessable();

    expect($wrongPassword->json('errors'))->toBe($unknownEmail->json('errors'));
    $this->assertGuest('web');
});

it('rate limits to 5 attempts a minute per email and IP', function () {
    foreach (range(1, 5) as $i) {
        login('tanvir@example.com', 'wrong password!!')->assertUnprocessable();
    }

    login('tanvir@example.com', 'wrong password!!')->assertStatus(429)->assertJson(['code' => 'rate_limited']);
});

it('locks the email for 15 minutes after 10 failures in an hour, even with the right password', function () {
    Notification::fake();
    $user = User::factory()->create();

    foreach (range(1, 10) as $i) {
        login($user->email, 'wrong password!!')->assertUnprocessable();
        $this->travel(13)->seconds(); // stay under the per-minute limiter
    }

    login($user->email)->assertStatus(423)->assertJson(['code' => 'account_locked']);
    Notification::assertSentTo($user, AccountLockedNotification::class);
    $this->assertGuest('web');

    $this->travel(16)->minutes();
    login($user->email)->assertOk();
});

it('locks unknown emails the same way, so a lock reveals nothing', function () {
    foreach (range(1, 10) as $i) {
        login('nobody@example.com', 'wrong password!!');
        $this->travel(13)->seconds();
    }

    login('nobody@example.com', 'anything at all')->assertStatus(423);
});

it('refuses suspended accounts only after a correct password', function () {
    $user = User::factory()->suspended()->create();

    login($user->email, 'wrong password!!')->assertUnprocessable();
    login($user->email)->assertForbidden()->assertJson(['code' => 'account_suspended']);
    $this->assertGuest('web');
});

it('keeps a remembered login for 30 days', function () {
    $user = User::factory()->create();

    $cookie = collect(login($user->email, remember: true)->headers->getCookies())
        ->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));

    expect($cookie)->not->toBeNull()
        ->and($cookie->getExpiresTime() - time())->toBeBetween(30 * 86400 - 60, 30 * 86400 + 60);
});

it('signs out', function () {
    $user = User::factory()->create();
    login($user->email)->assertOk();

    $this->postJson('/api/v1/auth/logout')->assertNoContent();

    $this->assertGuest('web');
});

it('stores passwords with argon2id', function () {
    $user = User::factory()->create(['password' => 'a brand new password']);

    expect($user->fresh()->password)->toStartWith('$argon2id$');
});
