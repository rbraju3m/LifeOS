<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Identity\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

const AUDIT_UA = 'LifeOS-AuditTest/1.0';

beforeEach(function () {
    Notification::fake();
    $this->withHeader('User-Agent', AUDIT_UA)->withServerVariables(['REMOTE_ADDR' => '203.0.113.7']);
});

/** @return list<string> the user's audit actions, oldest first */
function auditActions(User $user): array
{
    return AuditLog::query()->where('actor_user_id', $user->id)
        ->orderBy('created_at')->orderBy('id')->pluck('action')->all();
}

function auditLogin(string $email, string $password = UserFactory::PASSWORD, bool $remember = false)
{
    return test()->postJson('/api/v1/auth/login', compact('email', 'password', 'remember'));
}

function auditOtp(User $user, int $offset = 0): string
{
    $google2fa = app(Google2FA::class);

    return $google2fa->oathTotp(decrypt($user->fresh()->two_factor_secret), $google2fa->getTimestamp() + $offset);
}

function auditEnableTwoFactor(User $user): void
{
    test()->actingAs($user)
        ->postJson('/api/v1/auth/user/confirm-password', ['password' => UserFactory::PASSWORD])->assertCreated();
    test()->postJson('/api/v1/auth/user/two-factor-authentication')->assertOk();
    test()->postJson('/api/v1/auth/user/confirmed-two-factor-authentication', ['code' => auditOtp($user)])->assertOk();
}

it('records a registration', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Nasrin Akter',
        'email' => 'nasrin@example.com',
        'password' => 'a long enough passphrase',
        'password_confirmation' => 'a long enough passphrase',
        'accept_terms' => true,
    ])->assertStatus(202);

    $user = User::query()->where('email', 'nasrin@example.com')->sole();
    expect(auditActions($user))->toBe(['auth.registered']);
});

it('records a login with the IP and user agent, and without the password', function () {
    $user = User::factory()->create();

    auditLogin($user->email, remember: true)->assertOk();

    $log = AuditLog::query()->where('actor_user_id', $user->id)->where('action', 'auth.login')->sole();
    expect($log->ip)->toBe('203.0.113.7')
        ->and($log->user_agent)->toBe(AUDIT_UA)
        ->and($log->metadata)->toBe(['via_remember' => true])
        ->and($log->space_id)->toBeNull();

    $raw = json_encode(AuditLog::query()->toBase()->get()->map(fn ($r) => array_map(
        fn ($v) => is_string($v) && ! mb_check_encoding($v, 'UTF-8') ? bin2hex($v) : $v, (array) $r,
    )));
    expect($raw)->not->toContain(UserFactory::PASSWORD);
});

it('stores IPv6 addresses', function () {
    $user = User::factory()->create();

    $this->withServerVariables(['REMOTE_ADDR' => '2001:db8::1']);
    auditLogin($user->email)->assertOk();

    expect(AuditLog::query()->where('action', 'auth.login')->sole()->ip)->toBe('2001:db8::1');
});

it('cuts long user agents to 255 characters', function () {
    $user = User::factory()->create();

    $this->withHeader('User-Agent', str_repeat('x', 400));
    auditLogin($user->email)->assertOk();

    expect(mb_strlen((string) AuditLog::query()->where('action', 'auth.login')->sole()->user_agent))->toBe(255);
});

it('records a failed login for an existing account only', function () {
    $user = User::factory()->create();

    auditLogin($user->email, 'wrong password!!')->assertUnprocessable();
    auditLogin('nobody@example.com', 'wrong password!!')->assertUnprocessable();

    expect(auditActions($user))->toBe(['auth.registered', 'auth.login_failed'])
        ->and(AuditLog::query()->where('action', 'auth.login_failed')->count())->toBe(1)
        ->and(AuditLog::query()->whereNull('actor_user_id')->count())->toBe(0);
});

it('records the account lock', function () {
    $user = User::factory()->create();

    foreach (range(1, 10) as $i) {
        auditLogin($user->email, 'wrong password!!')->assertUnprocessable();
        $this->travel(13)->seconds();
    }

    expect(AuditLog::query()->where('actor_user_id', $user->id)->where('action', 'auth.login_failed')->count())->toBe(10);
    $lock = AuditLog::query()->where('actor_user_id', $user->id)->where('action', 'auth.account_locked')->sole();
    expect($lock->metadata)->toBe(['lock_minutes' => 15]);
});

it('records logout and signing out other sessions', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/v1/auth/sessions/logout-others', ['password' => UserFactory::PASSWORD])->assertNoContent();
    $this->postJson('/api/v1/auth/logout')->assertNoContent();

    expect(auditActions($user))->toBe(['auth.registered', 'auth.other_sessions_logged_out', 'auth.logout']);
});

it('records password changes and resets', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->putJson('/api/v1/auth/user/password', [
        'current_password' => UserFactory::PASSWORD,
        'password' => 'my new long password',
        'password_confirmation' => 'my new long password',
    ])->assertOk();
    $this->postJson('/api/v1/auth/logout')->assertNoContent();

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => app('auth.password.broker')->createToken($user),
        'email' => $user->email,
        'password' => 'another long password',
        'password_confirmation' => 'another long password',
    ])->assertOk();

    expect(auditActions($user))->toBe(['auth.registered', 'auth.password_changed', 'auth.logout', 'auth.password_reset']);
});

it('records email verification', function () {
    $user = User::factory()->unverified()->create();
    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);

    $this->actingAs($user)->getJson($url)->assertNoContent();

    expect(auditActions($user))->toContain('auth.email_verified');
});

it('records two-factor changes, failures and recovery code use', function () {
    $user = User::factory()->create();
    auditEnableTwoFactor($user);
    $this->postJson('/api/v1/auth/user/two-factor-recovery-codes')->assertOk();
    $this->postJson('/api/v1/auth/logout')->assertNoContent();

    auditLogin($user->email)->assertOk()->assertExactJson(['two_factor' => true]);
    $this->postJson('/api/v1/auth/two-factor-challenge', ['code' => '000000'])->assertUnprocessable();
    $code = json_decode(decrypt($user->fresh()->two_factor_recovery_codes), true)[0];
    $this->postJson('/api/v1/auth/two-factor-challenge', ['recovery_code' => $code])->assertOk();

    $this->postJson('/api/v1/auth/user/confirm-password', ['password' => UserFactory::PASSWORD])->assertCreated();
    $this->deleteJson('/api/v1/auth/user/two-factor-authentication')->assertOk();

    expect(auditActions($user))->toBe([
        'auth.registered',
        'auth.two_factor_enabled',
        'auth.recovery_codes_regenerated',
        'auth.logout',
        'auth.two_factor_failed',
        'auth.recovery_code_used',
        'auth.login',
        'auth.two_factor_disabled',
    ]);

    $recovery = AuditLog::query()->where('action', 'auth.recovery_code_used')->sole();
    expect($recovery->metadata)->toBeNull();
});

it('keeps entries append-only', function () {
    $user = User::factory()->create();
    $log = AuditLog::query()->where('actor_user_id', $user->id)->sole();

    expect(fn () => $log->forceFill(['action' => 'auth.tampered'])->save())->toThrow(LogicException::class)
        ->and(fn () => $log->delete())->toThrow(LogicException::class)
        ->and(AuditLog::query()->whereKey($log->id)->value('action'))->toBe('auth.registered');
});

it('shows the login in /me/security-events with the IP as text', function () {
    $user = User::factory()->create();

    auditLogin($user->email)->assertOk();

    $this->getJson('/api/v1/me/security-events')->assertOk()
        ->assertJsonPath('data.0.action', 'auth.login')
        ->assertJsonPath('data.0.ip', '203.0.113.7')
        ->assertJsonPath('data.0.user_agent', AUDIT_UA);
});
