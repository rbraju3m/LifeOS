<?php

use App\Modules\Identity\Models\User;
use App\Modules\Spaces\Enums\SpaceRole;
use App\Modules\Tags\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Content;
use Tests\Support\Spaces;

uses(RefreshDatabase::class);

function tagsUrl(string $spaceId, ?string $tagId = null): string
{
    return "/api/v1/spaces/{$spaceId}/tags".($tagId !== null ? "/{$tagId}" : '');
}

it('creates a tag owned by the caller in the space from the URL', function () {
    $user = User::factory()->create();
    $space = Spaces::personalOf($user);

    $response = $this->actingAs($user)->postJson(tagsUrl($space->id), ['name' => '  Bazar ', 'color' => '#AABBCC'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Bazar')
        ->assertJsonPath('data.color', '#aabbcc')
        ->assertJsonPath('data.space_id', $space->id)
        ->assertJsonPath('data.created_by', $user->id);

    expect($response->json('data.version'))->toBe(Tag::query()->sole()->updated_at->format('Uv'));
});

it('refuses server-owned and unknown fields', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($user)->postJson(tagsUrl(Spaces::personalOf($user)->id), [
        'name' => 'Bazar',
        'space_id' => Spaces::personalOf($other)->id,
        'created_by' => $other->id,
    ])->assertUnprocessable()->assertJsonValidationErrors(['space_id', 'created_by']);

    expect(Tag::query()->count())->toBe(0);
});

it('validates name and color', function (array $body, string $field) {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(tagsUrl(Spaces::personalOf($user)->id), $body)
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'missing name' => [[], 'name'],
    'empty name' => [['name' => '   '], 'name'],
    'long name' => [['name' => str_repeat('x', 51)], 'name'],
    'bad color' => [['name' => 'A', 'color' => 'red'], 'color'],
    'short color' => [['name' => 'A', 'color' => '#abc'], 'color'],
]);

it('keeps names unique per space, ignoring case', function () {
    $user = User::factory()->create();
    $personal = Spaces::personalOf($user);
    $family = Spaces::family($user);
    Content::tag($personal, $user, 'Bazar');

    $this->actingAs($user)->postJson(tagsUrl($personal->id), ['name' => 'bazar'])
        ->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->postJson(tagsUrl($family->id), ['name' => 'Bazar'])->assertCreated();
});

it('lists the space tags by name with a cursor', function () {
    $user = User::factory()->create();
    $space = Spaces::personalOf($user);
    foreach (['Office', 'bazar', 'Health'] as $name) {
        Content::tag($space, $user, $name);
    }
    Content::tag(Spaces::family($user), $user, 'Family only');

    $first = $this->actingAs($user)->getJson(tagsUrl($space->id).'?per_page=2')->assertOk();
    $second = $this->getJson(tagsUrl($space->id).'?per_page=2&cursor='.$first->json('meta.next_cursor'))->assertOk();

    expect([...$first->json('data.*.name'), ...$second->json('data.*.name')])->toBe(['bazar', 'Health', 'Office']);
});

it('updates and deletes a tag', function () {
    $user = User::factory()->create();
    $space = Spaces::personalOf($user);
    $tag = Content::tag($space, $user, 'Bazar');

    $this->actingAs($user)->patchJson(tagsUrl($space->id, $tag->id), ['color' => '#112233'])
        ->assertOk()->assertJsonPath('data.name', 'Bazar')->assertJsonPath('data.color', '#112233');
    $this->patchJson(tagsUrl($space->id, $tag->id), ['name' => 'bazar'])->assertOk(); // own name, new case
    $this->deleteJson(tagsUrl($space->id, $tag->id))->assertNoContent();

    expect(Tag::query()->count())->toBe(0);
});

it('answers 404 for a tag addressed through another space', function () {
    $user = User::factory()->create();
    $family = Spaces::family($user);
    $tag = Content::tag($family, $user, 'Bazar');

    $this->actingAs($user)->getJson(tagsUrl(Spaces::personalOf($user)->id, $tag->id))->assertNotFound();
    $this->patchJson(tagsUrl(Spaces::personalOf($user)->id, $tag->id), ['name' => 'X'])->assertNotFound();
});

it('lets members change only their own tags, and owners and admins any tag', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $family = Spaces::family($owner, [[$admin, SpaceRole::Admin], [$member, SpaceRole::Member]]);
    $ownersTag = Content::tag($family, $owner, 'Owner');

    $this->actingAs($member)->postJson(tagsUrl($family->id), ['name' => 'Mine'])->assertCreated();
    $mine = Tag::query()->where('name', 'Mine')->sole();
    $this->patchJson(tagsUrl($family->id, $mine->id), ['name' => 'Mine 2'])->assertOk();
    $this->patchJson(tagsUrl($family->id, $ownersTag->id), ['name' => 'Taken over'])->assertForbidden();
    $this->deleteJson(tagsUrl($family->id, $ownersTag->id))->assertForbidden();

    $this->actingAs($admin)->patchJson(tagsUrl($family->id, $mine->id), ['color' => '#000000'])->assertOk();
    $this->deleteJson(tagsUrl($family->id, $mine->id))->assertNoContent();
});

it('does not reveal through validation whether a name exists in a space the caller cannot write to', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $viewer = User::factory()->create();
    $family = Spaces::family($owner, [[$viewer, SpaceRole::Viewer]]);
    Content::tag($family, $owner, 'Secret');

    $this->actingAs($stranger)->postJson(tagsUrl($family->id), ['name' => 'Secret'])->assertNotFound();
    $this->actingAs($viewer)->postJson(tagsUrl($family->id), ['name' => 'Secret'])->assertForbidden();
});
