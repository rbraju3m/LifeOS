<?php

use App\Modules\Identity\Models\User;
use App\Modules\Tags\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\Support\Spaces;

uses(RefreshDatabase::class);

function idemPost(User $user, string $key, array $body)
{
    return test()->actingAs($user)->withHeader('Idempotency-Key', $key)
        ->postJson('/api/v1/spaces/'.Spaces::personalOf($user)->id.'/tags', $body);
}

it('replays the stored response for the same key and body', function () {
    $user = User::factory()->create();
    $key = (string) Str::uuid();

    $first = idemPost($user, $key, ['name' => 'Bazar'])->assertCreated();
    $second = idemPost($user, $key, ['name' => 'Bazar'])->assertCreated()->assertHeader('Idempotent-Replayed', 'true');

    expect($second->json())->toBe($first->json())
        ->and(Tag::query()->count())->toBe(1);
});

it('rejects the same key with a different body', function () {
    $user = User::factory()->create();
    $key = (string) Str::ulid();

    idemPost($user, $key, ['name' => 'Bazar'])->assertCreated();
    idemPost($user, $key, ['name' => 'Office'])->assertConflict()->assertJson(['code' => 'idempotency_key_reused']);

    expect(Tag::query()->count())->toBe(1);
});

it('scopes keys per user', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $key = (string) Str::uuid();

    $aliceTag = idemPost($alice, $key, ['name' => 'Bazar'])->assertCreated()->json('data.id');
    $bobTag = idemPost($bob, $key, ['name' => 'Bazar'])->assertCreated()->assertHeaderMissing('Idempotent-Replayed')->json('data.id');

    expect($bobTag)->not->toBe($aliceTag)
        ->and(Tag::query()->where('created_by', $bob->id)->count())->toBe(1);
});

it('does not store failed responses, so a fixed request can reuse the key', function () {
    $user = User::factory()->create();
    $key = (string) Str::uuid();

    idemPost($user, $key, ['name' => ''])->assertUnprocessable();
    idemPost($user, $key, ['name' => 'Bazar'])->assertCreated()->assertHeaderMissing('Idempotent-Replayed');
});

it('forgets keys after 24 hours', function () {
    $user = User::factory()->create();
    $key = (string) Str::uuid();

    idemPost($user, $key, ['name' => 'Bazar'])->assertCreated();
    $this->travel(24 * 3600 + 1)->seconds();
    idemPost($user, $key, ['name' => 'Office'])->assertCreated();
});

it('answers 409 while the first request with the key is still running', function () {
    $user = User::factory()->create();
    $key = (string) Str::uuid();
    $lock = Cache::lock('idem:'.$user->id.':'.$key.':lock', 30);
    $lock->get();

    idemPost($user, $key, ['name' => 'Bazar'])->assertConflict()->assertJson(['code' => 'idempotency_request_in_progress']);

    $lock->release();
});

it('rejects malformed keys', function (string $key) {
    idemPost(User::factory()->create(), $key, ['name' => 'Bazar'])
        ->assertStatus(400)->assertJson(['code' => 'idempotency_key_invalid']);
})->with(['abc', 'not-a-uuid-at-all-not-a-uuid-at-all', str_repeat('z', 26).'x']);

it('runs normally without the header', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/v1/spaces/'.Spaces::personalOf($user)->id.'/tags', ['name' => 'Bazar'])->assertCreated();
    $this->postJson('/api/v1/spaces/'.Spaces::personalOf($user)->id.'/tags', ['name' => 'Office'])->assertCreated();

    expect(Tag::query()->count())->toBe(2);
});
