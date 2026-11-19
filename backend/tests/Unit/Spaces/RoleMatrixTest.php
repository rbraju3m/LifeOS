<?php

use App\Modules\Spaces\Authorization\RoleMatrix;
use App\Modules\Spaces\Enums\SpaceRole;

/*
| The full permission table from docs/blueprint/14, written out so any change
| to RoleMatrix shows up here as a deliberate, reviewable diff.
| Columns: owner, admin, member, child, viewer. A string means "only for these
| content types" (checked with 'task', 'note', 'event', 'record').
*/
const EXPECTED = [
    'content.view' => [true, true, true, true, true],
    'content.create' => [true, true, true, 'task,note', false],
    'content.edit_own' => [true, true, true, true, false],
    'content.delete_own' => [true, true, true, true, false],
    'content.edit_any' => [true, true, 'task,task_list,event', false, false],
    'content.delete_any' => [true, true, false, false, false],
    'task.complete_assigned' => [true, true, true, true, false],
    'records.view' => [true, true, true, false, true],
    'records.reveal_secret_own' => [true, true, true, false, false],
    'records.reveal_secret_any' => [true, true, false, false, false],
    'members.invite' => [true, true, false, false, false],
    'members.manage' => [true, true, false, false, false],
    'space.settings' => [true, true, false, false, false],
    'space.delete' => [true, false, false, false, false],
    'space.transfer' => [true, false, false, false, false],
    'space.billing' => [true, false, false, false, false],
    'audit.view_space' => [true, true, false, false, false],
];

it('matches the blueprint permission table exactly', function () {
    $roles = [SpaceRole::Owner, SpaceRole::Admin, SpaceRole::Member, SpaceRole::Child, SpaceRole::Viewer];

    expect(RoleMatrix::abilities())->toEqualCanonicalizing(array_keys(EXPECTED));

    foreach (EXPECTED as $ability => $row) {
        foreach ($roles as $i => $role) {
            foreach (['task', 'task_list', 'note', 'event', 'record'] as $type) {
                $expected = is_string($row[$i]) ? in_array($type, explode(',', $row[$i]), true) : $row[$i];

                expect(RoleMatrix::allows($role, $ability, $type))
                    ->toBe($expected, "{$role->value} / {$ability} / {$type}");
            }
        }
    }
});

it('denies type-restricted grants when no content type is given', function () {
    expect(RoleMatrix::allows(SpaceRole::Child, 'content.create'))->toBeFalse()
        ->and(RoleMatrix::allows(SpaceRole::Owner, 'content.create'))->toBeTrue();
});

it('rejects unknown abilities instead of silently denying', function () {
    RoleMatrix::allows(SpaceRole::Owner, 'content.delete_everything');
})->throws(InvalidArgumentException::class);
