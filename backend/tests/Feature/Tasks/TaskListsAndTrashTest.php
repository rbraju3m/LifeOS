<?php

use App\Modules\Identity\Models\User;
use App\Modules\Spaces\Enums\SpaceRole;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Content;
use Tests\Support\Spaces;

uses(RefreshDatabase::class);

function listsUrl(string $spaceId, string $suffix = ''): string
{
    return "/api/v1/spaces/{$spaceId}/task-lists".$suffix;
}

it('creates, updates, archives and lists task lists', function () {
    $user = User::factory()->create();
    $space = Spaces::personalOf($user);

    $id = $this->actingAs($user)->postJson(listsUrl($space->id), ['name' => 'Home', 'color' => '#ABCDEF', 'icon' => 'house'])
        ->assertCreated()->assertJsonPath('data.color', '#abcdef')->json('data.id');
    $this->postJson(listsUrl($space->id), ['name' => 'Office', 'sort_order' => -1])->assertCreated();
    $this->postJson(listsUrl($space->id), ['name' => 'x', 'icon' => 'Bad Icon!'])->assertJsonValidationErrors('icon');

    $this->patchJson(listsUrl($space->id, "/{$id}"), ['name' => 'House'])->assertOk()->assertJsonPath('data.name', 'House');
    $this->getJson(listsUrl($space->id))->assertJsonPath('data.*.name', ['Office', 'House']);

    $this->postJson(listsUrl($space->id, "/{$id}/archive"))->assertOk()->assertJsonPath('data.archived_at', fn ($v) => $v !== null);
    $this->getJson(listsUrl($space->id))->assertJsonPath('data.*.name', ['Office']);
    $this->getJson(listsUrl($space->id).'?filter[archived]=1')->assertJsonPath('data.*.name', ['House']);
    $this->postJson(listsUrl($space->id, "/{$id}/unarchive"))->assertJsonPath('data.archived_at', null);
});

it('does not let children create lists', function () {
    $owner = User::factory()->create();
    $child = User::factory()->create();
    $family = Spaces::family($owner, [[$child, SpaceRole::Child]]);

    $this->actingAs($child)->postJson(listsUrl($family->id), ['name' => 'Mine'])->assertForbidden();
});

it('trashes, restores and permanently deletes a task', function () {
    $user = User::factory()->create();
    $space = Spaces::personalOf($user);
    $task = Content::task($space, $user, 'T');
    $url = "/api/v1/spaces/{$space->id}/tasks/{$task->id}";

    $this->actingAs($user)->deleteJson($url.'/force')->assertConflict()->assertJson(['code' => 'not_in_trash']);
    $this->deleteJson($url)->assertNoContent();
    $this->getJson($url)->assertNotFound();
    $this->getJson("/api/v1/spaces/{$space->id}/tasks?filter[trashed]=only")->assertJsonPath('data.0.id', $task->id);

    $this->postJson($url.'/restore')->assertOk()->assertJsonPath('data.deleted_at', null);
    $this->deleteJson($url);
    $this->deleteJson($url.'/force')->assertNoContent();
    expect(Task::withTrashed()->count())->toBe(0);
});

it('trashes a list with its tasks and restores exactly those', function () {
    $user = User::factory()->create();
    $space = Spaces::personalOf($user);
    $list = Content::taskList($space, $user, 'Home');
    $inList = Content::task($space, $user, 'In list', attributes: ['task_list_id' => $list->id]);
    $trashedBefore = Content::task($space, $user, 'Trashed earlier', attributes: ['task_list_id' => $list->id]);
    $outside = Content::task($space, $user, 'Outside');
    $trashedBefore->delete();
    $this->travel(5)->seconds();

    $this->actingAs($user)->deleteJson(listsUrl($space->id, "/{$list->id}"))->assertNoContent();
    expect($inList->fresh()->trashed())->toBeTrue()->and($outside->fresh()->trashed())->toBeFalse();

    $this->travel(5)->seconds();
    $this->postJson(listsUrl($space->id, "/{$list->id}/restore"))->assertOk()->assertJsonPath('data.deleted_at', null);

    expect($inList->fresh()->trashed())->toBeFalse()
        ->and($trashedBefore->fresh()->trashed())->toBeTrue();
});

it("moves other members' private tasks out of a deleted list instead of trashing them", function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $family = Spaces::family($owner, [[$member, SpaceRole::Member]]);
    $list = Content::taskList($family, $owner, 'Shared');
    $private = Content::task($family, $member, 'Private', true, ['task_list_id' => $list->id]);

    $this->actingAs($owner)->deleteJson(listsUrl($family->id, "/{$list->id}"))->assertNoContent();

    expect($private->fresh())->trashed()->toBeFalse()->task_list_id->toBeNull();
});

it('restores a task whose list is still in the Trash outside the list', function () {
    $user = User::factory()->create();
    $space = Spaces::personalOf($user);
    $list = Content::taskList($space, $user, 'Home');
    $task = Content::task($space, $user, 'T', attributes: ['task_list_id' => $list->id]);

    $this->actingAs($user)->deleteJson(listsUrl($space->id, "/{$list->id}"));
    $this->postJson("/api/v1/spaces/{$space->id}/tasks/{$task->id}/restore")
        ->assertOk()->assertJsonPath('data.task_list_id', null);
    expect(TaskList::onlyTrashed()->count())->toBe(1);
});
