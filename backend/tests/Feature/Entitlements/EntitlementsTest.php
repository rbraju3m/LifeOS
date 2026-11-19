<?php

use App\Modules\Entitlements\Entitlements;
use App\Modules\Entitlements\FeatureCode as F;
use App\Modules\Entitlements\Models\EntitlementOverride;
use App\Modules\Entitlements\Models\Feature;
use App\Modules\Entitlements\Models\Plan;
use App\Modules\Entitlements\Models\Subscription;
use App\Modules\Identity\Models\User;
use App\Modules\Shared\Http\Problems\ApiProblemException;
use App\Modules\Spaces\Models\Space;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Spaces;

uses(RefreshDatabase::class);

function subscribe(Space $space, string $planCode, string $status = 'active'): Subscription
{
    $subscription = new Subscription;
    $subscription->forceFill([
        'space_id' => $space->id,
        'plan_id' => Plan::query()->where('code', $planCode)->value('id'),
        'status' => $status,
        'starts_at' => now(),
    ])->save();

    return $subscription;
}

function override(Space $space, string $featureCode, ?int $limit = null, ?bool $bool = null, ?DateTimeInterface $expiresAt = null): EntitlementOverride
{
    $override = new EntitlementOverride;
    $override->forceFill([
        'space_id' => $space->id,
        'feature_id' => Feature::query()->where('code', $featureCode)->value('id'),
        'limit_value' => $limit, 'bool_value' => $bool,
        'reason' => 'test', 'expires_at' => $expiresAt,
    ])->save();

    return $override;
}

function entitlements(): Entitlements
{
    return app(Entitlements::class);
}

it('puts a personal space without a subscription on the free plan', function () {
    $space = Spaces::personalOf(User::factory()->create());
    $resolved = entitlements()->for($space);

    expect($resolved->planCode)->toBe('free')
        ->and($resolved->limit(F::RECORDS_MAX_COUNT))->toBe(50)
        ->and($resolved->limit(F::STORAGE_MAX_BYTES))->toBe(500 * 1024 * 1024)
        ->and($resolved->allows(F::EXPORT_ENABLED))->toBeTrue();
});

it('puts a family space without a subscription on the free family plan', function () {
    $space = Spaces::family(User::factory()->create());

    expect(entitlements()->for($space)->planCode)->toBe('free_family')
        ->and(entitlements()->for($space)->limit(F::SPACE_MAX_MEMBERS))->toBe(3);
});

it('uses the live subscription plan, with null meaning unlimited', function () {
    $space = Spaces::personalOf(User::factory()->create());
    subscribe($space, 'personal_plus');

    $resolved = entitlements()->for($space);
    expect($resolved->planCode)->toBe('personal_plus')
        ->and($resolved->limit(F::RECORDS_MAX_COUNT))->toBeNull()
        ->and($resolved->limit(F::REMINDERS_SMS_PER_MONTH))->toBe(30);
});

it('ignores cancelled and expired subscriptions', function (string $status) {
    $space = Spaces::personalOf(User::factory()->create());
    subscribe($space, 'personal_plus', $status);

    expect(entitlements()->for($space)->planCode)->toBe('free');
})->with(['cancelled', 'expired']);

it('allows only one live subscription per space', function () {
    $space = Spaces::personalOf(User::factory()->create());
    subscribe($space, 'personal_plus');
    subscribe($space, 'personal_plus', 'cancelled'); // history rows are fine

    expect(fn () => subscribe($space, 'personal_plus', 'trialing'))->toThrow(UniqueConstraintViolationException::class);
});

it('lets an override beat the plan, until it expires', function () {
    $space = Spaces::personalOf(User::factory()->create());
    override($space, F::RECORDS_MAX_COUNT, limit: 200, expiresAt: now()->addDay());

    expect(entitlements()->for($space)->limit(F::RECORDS_MAX_COUNT))->toBe(200);

    $this->travel(2)->days();
    expect(entitlements()->for($space)->limit(F::RECORDS_MAX_COUNT))->toBe(50);
});

it('picks up subscription and override changes immediately despite the cache', function () {
    $space = Spaces::personalOf(User::factory()->create());
    expect(entitlements()->for($space)->planCode)->toBe('free'); // now cached

    $subscription = subscribe($space, 'personal_plus');
    expect(entitlements()->for($space)->planCode)->toBe('personal_plus');

    $subscription->forceFill(['status' => 'cancelled'])->save();
    expect(entitlements()->for($space)->planCode)->toBe('free');

    override($space, F::SPACE_MAX_MEMBERS, limit: 2);
    expect(entitlements()->for($space)->limit(F::SPACE_MAX_MEMBERS))->toBe(2);
});

it('always allows data export, even if a plan or override says otherwise', function () {
    $space = Spaces::personalOf(User::factory()->create());
    DB::table('plan_features')
        ->where('feature_id', Feature::query()->where('code', F::EXPORT_ENABLED)->value('id'))
        ->update(['bool_value' => false]);
    override($space, F::EXPORT_ENABLED, bool: false);

    expect(entitlements()->for($space)->allows(F::EXPORT_ENABLED))->toBeTrue();
});

it('blocks creating beyond a limit with entitlement_exceeded', function () {
    $space = Spaces::personalOf(User::factory()->create());

    entitlements()->assertCanCreate($space, F::RECORDS_MAX_COUNT, 49); // the 50th is fine

    try {
        entitlements()->assertCanCreate($space, F::RECORDS_MAX_COUNT, 50);
        $this->fail('Expected entitlement_exceeded');
    } catch (ApiProblemException $e) {
        expect($e->status)->toBe(403)
            ->and($e->errorCode)->toBe('entitlement_exceeded')
            ->and($e->meta)->toBe(['feature' => F::RECORDS_MAX_COUNT, 'limit' => 50]);
    }
});

it('never blocks unlimited features', function () {
    $space = Spaces::personalOf(User::factory()->create());
    subscribe($space, 'personal_plus');

    entitlements()->assertCanCreate($space, F::RECORDS_MAX_COUNT, 1_000_000);
    expect(true)->toBeTrue();
});

it('rejects asking for a limit as a boolean and unknown features', function () {
    $resolved = entitlements()->for(Spaces::personalOf(User::factory()->create()));

    expect(fn () => $resolved->allows(F::RECORDS_MAX_COUNT))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $resolved->limit('made.up'))->toThrow(InvalidArgumentException::class);
});

it("shows a member their space's entitlements and usage", function () {
    $user = User::factory()->create();
    $space = Spaces::personalOf($user);

    $response = $this->actingAs($user)->getJson("/api/v1/spaces/{$space->id}/entitlements")
        ->assertOk()
        ->assertJsonPath('data.plan', 'free')
        ->assertJsonPath('data.usage.members', 1)
        ->assertJsonPath('data.usage.storage_used_bytes', 0);

    // Feature codes contain dots, so read the object rather than a dot path.
    expect($response->json('data.features')[F::RECORDS_MAX_COUNT])->toBe(50);
});
