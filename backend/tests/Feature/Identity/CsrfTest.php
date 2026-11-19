<?php

use App\Modules\Identity\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Laravel skips CSRF checks in tests; this subclass turns them back on. */
class EnforcedCsrf extends ValidateCsrfToken
{
    protected function runningUnitTests(): bool
    {
        return false;
    }
}

beforeEach(fn () => config(['sanctum.middleware.validate_csrf_token' => EnforcedCsrf::class]));

it('rejects a state-changing SPA request without a CSRF token', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => UserFactory::PASSWORD])
        ->assertStatus(419)
        ->assertJson(['code' => 'csrf_mismatch']);
    $this->assertGuest('web');
});

it('accepts the request with the XSRF token from the session', function () {
    $user = User::factory()->create();
    $this->withSession(['_token' => 'known-token']);

    $this->withHeader('X-CSRF-TOKEN', 'known-token')
        ->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => UserFactory::PASSWORD])
        ->assertOk();
});

it('accepts same-origin browser requests (Sec-Fetch-Site)', function () {
    $user = User::factory()->create();

    $this->withHeader('Sec-Fetch-Site', 'same-origin')
        ->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => UserFactory::PASSWORD])
        ->assertOk();
});
