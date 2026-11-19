<?php

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Notifications\RegistrationAttemptNotification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Support\FakePwnedPasswords;

uses(RefreshDatabase::class);

function registration(array $overrides = []): array
{
    return [
        'name' => 'Nasrin Akter',
        'email' => 'Nasrin@Example.com',
        'password' => 'a long enough passphrase',
        'password_confirmation' => 'a long enough passphrase',
        'locale' => 'bn',
        'timezone' => 'Asia/Dhaka',
        'accept_terms' => true,
        ...$overrides,
    ];
}

it('creates the account with preferences and consents and sends a verification email', function () {
    Notification::fake();

    $this->postJson('/api/v1/auth/register', registration())
        ->assertStatus(202)
        ->assertJsonStructure(['message']);

    $user = User::query()->sole();
    expect($user->email)->toBe('nasrin@example.com')
        ->and($user->locale)->toBe('bn')
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->password)->toStartWith('$argon2id$')
        ->and($user->preferences)->not->toBeNull()
        ->and($user->consents()->pluck('document')->sort()->values()->all())->toBe(['privacy', 'terms']);

    Notification::assertSentTo($user, VerifyEmail::class);
    $this->assertGuest('web');
});

it('gives the same response for an existing email and emails the owner instead', function () {
    Notification::fake();
    $existing = User::factory()->create(['email' => 'nasrin@example.com']);

    $new = $this->postJson('/api/v1/auth/register', registration(['email' => 'someone-new@example.com']));
    $dupe = $this->postJson('/api/v1/auth/register', registration());

    expect($dupe->status())->toBe($new->status())
        ->and($dupe->json())->toBe($new->json())
        ->and(User::query()->where('email', 'nasrin@example.com')->count())->toBe(1);

    Notification::assertSentTo($existing, RegistrationAttemptNotification::class);
    Notification::assertNotSentTo($existing, VerifyEmail::class);
});

it('validates the registration', function (array $overrides, string $field) {
    $this->postJson('/api/v1/auth/register', registration($overrides))
        ->assertUnprocessable()
        ->assertJson(['code' => 'validation_failed'])
        ->assertJsonValidationErrors($field);
})->with([
    'short password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
    'unconfirmed password' => [['password_confirmation' => 'something else entirely'], 'password'],
    'terms not accepted' => [['accept_terms' => false], 'accept_terms'],
    'bad email' => [['email' => 'not-an-email'], 'email'],
    'unknown locale' => [['locale' => 'fr'], 'locale'],
    'offset instead of IANA zone' => [['timezone' => '+06:00'], 'timezone'],
    'server-owned field' => [['status' => 'active'], 'status'],
]);

it('rejects a password found in breach data', function () {
    FakePwnedPasswords::breach('a long enough passphrase');

    $this->postJson('/api/v1/auth/register', registration())->assertJsonValidationErrors('password');
});

it('limits registrations per IP to 10 an hour', function () {
    Notification::fake();

    foreach (range(1, 10) as $i) {
        $this->postJson('/api/v1/auth/register', registration(['email' => "user{$i}@example.com"]))->assertStatus(202);
    }

    $this->postJson('/api/v1/auth/register', registration(['email' => 'user11@example.com']))
        ->assertStatus(429)
        ->assertJson(['code' => 'rate_limited']);
});
