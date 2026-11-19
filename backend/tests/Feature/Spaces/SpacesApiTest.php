<?php

use App\Modules\Identity\Models\User;
use App\Modules\Spaces\Enums\MemberStatus;
use App\Modules\Spaces\Enums\SpaceRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Spaces;

uses(RefreshDatabase::class);

it('lists only the spaces the user is an active member of, personal first', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $family = Spaces::family($other, [[$user, SpaceRole::Member]]);
    $leftFamily = Spaces::family($other);
    Spaces::add($leftFamily, $user, SpaceRole::Member, MemberStatus::Removed);

    $response = $this->actingAs($user)->getJson('/api/v1/spaces')->assertOk();

    expect($response->json('data.*.id'))->toBe([Spaces::personalOf($user)->id, $family->id])
        ->and($response->json('data.0.my_role'))->toBe('owner')
        ->and($response->json('data.1.my_role'))->toBe('member');
});

it('shows a space to its members', function () {
    $user = User::factory()->create();
    $space = Spaces::personalOf($user);

    $this->actingAs($user)->getJson("/api/v1/spaces/{$space->id}")
        ->assertOk()
        ->assertJsonPath('data.type', 'personal');
});

it('answers 404 for spaces the user cannot see, so existence is not revealed', function (string $case) {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $space = match ($case) {
        "another user's personal space" => Spaces::personalOf($other),
        'a family space they are not in' => Spaces::family($other),
        'a family space they were removed from' => tap(Spaces::family($other), fn ($s) => Spaces::add($s, $user, SpaceRole::Member, MemberStatus::Removed)),
        'a deleted family space' => tap(Spaces::family($user), fn ($s) => $s->delete()),
    };

    $this->actingAs($user)->getJson("/api/v1/spaces/{$space->id}")->assertNotFound()->assertJson(['code' => 'not_found']);
    $this->getJson("/api/v1/spaces/{$space->id}/entitlements")->assertNotFound();
})->with([
    "another user's personal space",
    'a family space they are not in',
    'a family space they were removed from',
    'a deleted family space',
]);

it('answers 404 for ids that do not exist or are malformed', function (string $id) {
    $this->actingAs(User::factory()->create())->getJson("/api/v1/spaces/{$id}")->assertNotFound();
})->with(['01kaaaaaaaaaaaaaaaaaaaaaaa', 'not-a-ulid', "1' OR '1'='1"]);

it('requires authentication', function () {
    $space = Spaces::personalOf(User::factory()->create());

    $this->getJson('/api/v1/spaces')->assertUnauthorized();
    $this->getJson("/api/v1/spaces/{$space->id}")->assertUnauthorized();
    $this->getJson("/api/v1/spaces/{$space->id}/entitlements")->assertUnauthorized();
});
