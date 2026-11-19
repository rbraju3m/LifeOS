<?php

use App\Modules\Identity\Models\User;
use App\Modules\Spaces\Enums\MemberStatus;
use App\Modules\Spaces\Enums\SpaceRole;
use App\Modules\Spaces\Enums\Visibility;
use App\Modules\Spaces\Models\Space;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Fixtures\Spaces\FixtureNote;
use Tests\Fixtures\Spaces\FixtureNotePolicy;
use Tests\Support\Spaces;

uses(RefreshDatabase::class);

/*
| The authorization core every content module builds on: the visibleTo()
| query scope and SpaceContentPolicy, exercised through a fixture "note".
*/

beforeEach(fn () => Gate::policy(FixtureNote::class, FixtureNotePolicy::class));

function note(Space $space, User $creator, Visibility $visibility = Visibility::Space): FixtureNote
{
    $note = new FixtureNote;
    $note->forceFill([
        'space_id' => $space->id, 'created_by' => $creator->id,
        'visibility' => $visibility, 'title' => 'n',
    ])->save();

    return $note;
}

function visibleIds(User $user): array
{
    return FixtureNote::query()->visibleTo($user)->pluck('id')->sort()->values()->all();
}

it("never shows anything from another user's personal space", function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $note = note(Spaces::personalOf($alice), $alice);

    expect(visibleIds($bob))->toBe([])
        ->and(Gate::forUser($bob)->inspect('view', $note)->status())->toBe(404)
        ->and(Gate::forUser($alice)->allows('view', $note))->toBeTrue();
});

it("hides a member's private items from everyone else in the family, owners and admins included", function () {
    [$owner, $admin, $member] = User::factory()->count(3)->create()->all();
    $family = Spaces::family($owner, [[$admin, SpaceRole::Admin], [$member, SpaceRole::Member]]);
    $shared = note($family, $member);
    $private = note($family, $member, Visibility::Private);

    foreach ([$owner, $admin] as $other) {
        expect(visibleIds($other))->toBe([$shared->id])
            ->and(Gate::forUser($other)->inspect('view', $private)->status())->toBe(404)
            ->and(Gate::forUser($other)->inspect('update', $private)->status())->toBe(404)
            ->and(Gate::forUser($other)->inspect('delete', $private)->status())->toBe(404);
    }

    expect(visibleIds($member))->toBe(collect([$shared->id, $private->id])->sort()->values()->all());
});

it('stops showing a space once the member is removed', function () {
    [$owner, $member] = User::factory()->count(2)->create()->all();
    $family = Spaces::family($owner);
    $membership = Spaces::add($family, $member, SpaceRole::Member);
    $note = note($family, $owner);
    expect(Gate::forUser($member)->allows('view', $note))->toBeTrue();

    $membership->forceFill(['status' => MemberStatus::Removed])->save();

    expect(visibleIds($member))->toBe([])
        ->and(Gate::forUser($member)->inspect('view', $note)->status())->toBe(404);
});

it('applies the role matrix to create, update and delete', function () {
    [$owner, $admin, $member, $child, $viewer] = User::factory()->count(5)->create()->all();
    $family = Spaces::family($owner, [
        [$admin, SpaceRole::Admin], [$member, SpaceRole::Member],
        [$child, SpaceRole::Child], [$viewer, SpaceRole::Viewer],
    ]);
    $ownersNote = note($family, $owner);
    $childsNote = note($family, $child);

    // create (notes): everyone but viewers; children may create notes.
    expect(Gate::forUser($member)->allows('create', [FixtureNote::class, $family]))->toBeTrue()
        ->and(Gate::forUser($child)->allows('create', [FixtureNote::class, $family]))->toBeTrue()
        ->and(Gate::forUser($viewer)->inspect('create', [FixtureNote::class, $family])->status())->toBe(403);

    // edit someone else's note: owner/admin yes; member no (members may edit any task/event, not notes).
    expect(Gate::forUser($admin)->allows('update', $ownersNote))->toBeTrue()
        ->and(Gate::forUser($member)->inspect('update', $ownersNote)->status())->toBe(403)
        ->and(Gate::forUser($viewer)->inspect('update', $ownersNote)->status())->toBe(403);

    // own items: a child can edit and delete their own note, not others'.
    expect(Gate::forUser($child)->allows('update', $childsNote))->toBeTrue()
        ->and(Gate::forUser($child)->allows('delete', $childsNote))->toBeTrue()
        ->and(Gate::forUser($child)->inspect('delete', $ownersNote)->status())->toBe(403);

    // delete any: owner and admin only.
    expect(Gate::forUser($owner)->allows('delete', $childsNote))->toBeTrue()
        ->and(Gate::forUser($member)->inspect('delete', $childsNote)->status())->toBe(403);
});

it('answers 404 to creating in a space the user is not a member of', function () {
    $space = Spaces::personalOf(User::factory()->create());

    expect(Gate::forUser(User::factory()->create())->inspect('create', [FixtureNote::class, $space])->status())->toBe(404);
});
