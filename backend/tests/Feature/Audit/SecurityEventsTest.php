<?php

use App\Modules\Audit\AuditLogger;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires authentication', function () {
    $this->getJson('/api/v1/me/security-events')->assertUnauthorized();
});

it('lists only my own security events, newest first', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    $audit = app(AuditLogger::class);

    $this->travel(1)->minutes();
    $audit->record('auth.login', $me, metadata: ['via_remember' => false]);
    $this->travel(1)->minutes();
    $audit->record('auth.password_changed', $me);
    $audit->record('export.requested', $me);   // not a security event
    $audit->record('auth.login', $other);      // someone else's

    $response = $this->actingAs($me)->getJson('/api/v1/me/security-events')->assertOk();

    expect($response->json('data.*.action'))->toBe(['auth.password_changed', 'auth.login', 'auth.registered'])
        ->and($response->json('data.1.metadata'))->toBe(['via_remember' => false])
        ->and($response->json('data.0.metadata'))->toBe([])
        ->and(array_keys($response->json('data.0')))->toBe(['id', 'action', 'occurred_at', 'ip', 'user_agent', 'metadata']);
});

it('paginates with a cursor', function () {
    $me = User::factory()->create();
    // Same second, different milliseconds: the cursor must not drop them.
    foreach ([100, 300, 500, 700] as $ms) {
        $this->travelTo(CarbonImmutable::parse("2026-11-20 10:00:00.{$ms}"));
        app(AuditLogger::class)->record('auth.login', $me);
    }
    $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00:05'));

    $first = $this->actingAs($me)->getJson('/api/v1/me/security-events?per_page=3')->assertOk();
    expect($first->json('data'))->toHaveCount(3);

    $second = $this->getJson('/api/v1/me/security-events?per_page=3&cursor='.$first->json('meta.next_cursor'))->assertOk();
    expect($second->json('data'))->toHaveCount(2)
        ->and($second->json('data.1.action'))->toBe('auth.registered')
        ->and(array_intersect($first->json('data.*.id'), $second->json('data.*.id')))->toBe([]);
});

it('prunes entries past their retention and keeps the rest', function () {
    $user = User::factory()->create();
    $audit = app(AuditLogger::class);

    $this->travelTo(now()->subDays(800));
    $oldSecurity = $audit->record('auth.login', $user);
    $this->travelBack();
    $this->travelTo(now()->subDays(400));
    $keptSecurity = $audit->record('auth.login', $user);
    $oldOther = $audit->record('export.requested', $user);
    $this->travelBack();
    $this->travelTo(now()->subDays(100));
    $keptOther = $audit->record('export.requested', $user);
    $this->travelBack();

    $this->artisan('audit:prune', ['--chunk' => 1])->assertSuccessful();

    $remaining = AuditLog::query()->pluck('id');
    expect($remaining)->not->toContain($oldSecurity->id)
        ->not->toContain($oldOther->id)
        ->toContain($keptSecurity->id)
        ->toContain($keptOther->id);
});

it('schedules pruning monthly', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('audit:prune')->assertSuccessful();
});
