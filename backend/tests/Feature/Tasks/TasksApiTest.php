<?php

use App\Modules\Identity\Models\User;
use App\Modules\Spaces\Enums\SpaceRole;
use App\Modules\Tasks\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Content;
use Tests\Support\Spaces;

uses(RefreshDatabase::class);

function tasksUrl(string $spaceId, string $suffix = ''): string
{
    return "/api/v1/spaces/{$spaceId}/tasks".$suffix;
}

it('creates a task with server-owned fields set by the server', function () {
    $user = User::factory()->create(['timezone' => 'Asia/Dhaka']);
    $space = Spaces::personalOf($user);
    $list = Content::taskList($space, $user, 'Home');
    $tag = Content::tag($space, $user, 'Bazar');

    $this->actingAs($user)->postJson(tasksUrl($space->id), [
        'title' => 'Buy rice',
        'description' => '5 kg',
        'priority' => 2,
        'task_list_id' => $list->id,
        'tag_ids' => [$tag->id],
        'due_date' => '2026-12-01',
        'due_time' => '09:30',
    ])->assertCreated()
        ->assertJsonPath('data.title', 'Buy rice')
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.created_by', $user->id)
        ->assertJsonPath('data.space_id', $space->id)
        ->assertJsonPath('data.timezone', 'Asia/Dhaka')
        ->assertJsonPath('data.due_date', '2026-12-01')
        ->assertJsonPath('data.due_time', '09:30')
        ->assertJsonPath('data.due_at', '2026-12-01T03:30:00.000000Z')
        ->assertJsonPath('data.tags', [['id' => $tag->id, 'name' => 'Bazar', 'color' => null]]);
});

it('stores the due instant in the given timezone, across DST', function () {
    $user = User::factory()->create();
    $space = Spaces::personalOf($user);

    $this->actingAs($user)->postJson(tasksUrl($space->id), [
        'title' => 'Call', 'due_date' => '2026-07-01', 'due_time' => '09:00', 'timezone' => 'America/New_York',
    ])->assertJsonPath('data.due_at', '2026-07-01T13:00:00.000000Z');

    $this->postJson(tasksUrl($space->id), [
        'title' => 'Call', 'due_date' => '2026-12-01', 'due_time' => '09:00', 'timezone' => 'America/New_York',
    ])->assertJsonPath('data.due_at', '2026-12-01T14:00:00.000000Z')->assertJsonPath('data.due_time', '09:00');
});

it('validates task input', function (array $body, string $field) {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(tasksUrl(Spaces::personalOf($user)->id), ['title' => 'T', ...$body])
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'no title' => [['title' => ''], 'title'],
    'long title' => [['title' => str_repeat('x', 256)], 'title'],
    'long description' => [['description' => str_repeat('x', 10001)], 'description'],
    'priority' => [['priority' => 4], 'priority'],
    'time without date' => [['due_time' => '09:00'], 'due_date'],
    'bad time' => [['due_date' => '2026-12-01', 'due_time' => '25:00'], 'due_time'],
    'bad timezone' => [['timezone' => 'Mars/Olympus'], 'timezone'],
    'server-owned field' => [['status' => 'completed'], 'status'],
    'created_by' => [['created_by' => '01m7s000000000000000000000'], 'created_by'],
    'too many tags' => [['tag_ids' => array_map(fn ($i) => str_pad((string) $i, 26, '0'), range(1, 21))], 'tag_ids'],
]);

it('refuses lists, tags and assignees from outside the space', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $personal = Spaces::personalOf($user);
    $family = Spaces::family($user);
    $foreignList = Content::taskList($family, $user, 'Family list');
    $foreignTag = Content::tag($family, $user, 'Family tag');
    $othersPrivateList = Content::taskList(Spaces::family($other, [[$user, SpaceRole::Member]]), $other, 'Secret', private: true);

    $this->actingAs($user);
    $this->postJson(tasksUrl($personal->id), ['title' => 'T', 'task_list_id' => $foreignList->id])->assertJsonValidationErrors('task_list_id');
    $this->postJson(tasksUrl($personal->id), ['title' => 'T', 'tag_ids' => [$foreignTag->id]])->assertJsonValidationErrors('tag_ids');
    $this->postJson(tasksUrl($personal->id), ['title' => 'T', 'assignee_user_id' => $other->id])->assertJsonValidationErrors('assignee_user_id');
    $this->postJson(tasksUrl($othersPrivateList->space_id), ['title' => 'T', 'task_list_id' => $othersPrivateList->id])
        ->assertJsonValidationErrors('task_list_id');
});

it('assigns tasks to active members, and private tasks only to their creator', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $family = Spaces::family($owner, [[$member, SpaceRole::Member]]);

    $this->actingAs($owner)->postJson(tasksUrl($family->id), ['title' => 'Dishes', 'assignee_user_id' => $member->id])
        ->assertCreated()->assertJsonPath('data.assignee_user_id', $member->id);
    $this->postJson(tasksUrl($family->id), ['title' => 'Mine', 'visibility' => 'private', 'assignee_user_id' => $member->id])
        ->assertJsonValidationErrors('assignee_user_id');

    $task = Task::query()->where('title', 'Dishes')->sole();
    $this->patchJson(tasksUrl($family->id, "/{$task->id}"), ['visibility' => 'private'])
        ->assertJsonValidationErrors('assignee_user_id');
});

it('updates fields, tags and due time, keeping local time when only the timezone changes', function () {
    $user = User::factory()->create(['timezone' => 'Asia/Dhaka']);
    $space = Spaces::personalOf($user);
    $a = Content::tag($space, $user, 'A');
    $b = Content::tag($space, $user, 'B');
    $id = $this->actingAs($user)->postJson(tasksUrl($space->id), [
        'title' => 'T', 'tag_ids' => [$a->id], 'due_date' => '2026-12-01', 'due_time' => '09:00',
    ])->json('data.id');

    $this->patchJson(tasksUrl($space->id, "/{$id}"), ['title' => 'T2', 'tag_ids' => [$b->id]])
        ->assertOk()->assertJsonPath('data.title', 'T2')->assertJsonPath('data.tags.0.id', $b->id)
        ->assertJsonPath('data.due_time', '09:00');

    $this->patchJson(tasksUrl($space->id, "/{$id}"), ['timezone' => 'Europe/London'])
        ->assertJsonPath('data.due_time', '09:00')->assertJsonPath('data.due_at', '2026-12-01T09:00:00.000000Z');

    $this->patchJson(tasksUrl($space->id, "/{$id}"), ['due_date' => '2026-12-05'])
        ->assertJsonPath('data.due_date', '2026-12-05')->assertJsonPath('data.due_time', null)->assertJsonPath('data.due_at', null);

    $this->patchJson(tasksUrl($space->id, "/{$id}"), ['due_date' => null, 'tag_ids' => []])
        ->assertJsonPath('data.due_date', null)->assertJsonPath('data.tags', []);
    expect(DB::table('task_tag')->count())->toBe(0);
});

it('lets only the creator change visibility', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $family = Spaces::family($owner, [[$member, SpaceRole::Member]]);
    $task = Content::task($family, $member, "Member's task");

    $this->actingAs($owner)->patchJson(tasksUrl($family->id, "/{$task->id}"), ['visibility' => 'private'])
        ->assertForbidden();
    $this->actingAs($member)->patchJson(tasksUrl($family->id, "/{$task->id}"), ['visibility' => 'private'])
        ->assertOk()->assertJsonPath('data.visibility', 'private');
    $this->actingAs($owner)->getJson(tasksUrl($family->id, "/{$task->id}"))->assertNotFound();
});

it('lets members edit any space task but delete only their own', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $family = Spaces::family($owner, [[$member, SpaceRole::Member]]);
    $task = Content::task($family, $owner, "Owner's task");

    $this->actingAs($member)->patchJson(tasksUrl($family->id, "/{$task->id}"), ['title' => 'Edited'])->assertOk();
    $this->deleteJson(tasksUrl($family->id, "/{$task->id}"))->assertForbidden();
});

it('completes and reopens, idempotently, including by the assignee', function () {
    $owner = User::factory()->create();
    $child = User::factory()->create();
    $family = Spaces::family($owner, [[$child, SpaceRole::Child]]);
    $task = Content::task($family, $owner, 'Homework', attributes: ['assignee_user_id' => $child->id]);
    $other = Content::task($family, $owner, 'Not yours');

    $this->actingAs($child)->postJson(tasksUrl($family->id, "/{$task->id}/complete"))
        ->assertOk()->assertJsonPath('data.status', 'completed')->assertJsonPath('data.completed_by', $child->id);
    $completedAt = $task->fresh()->completed_at;
    $this->travel(1)->minutes();
    $this->postJson(tasksUrl($family->id, "/{$task->id}/complete"))->assertOk();
    expect($task->fresh()->completed_at->equalTo($completedAt))->toBeTrue();

    $this->postJson(tasksUrl($family->id, "/{$other->id}/complete"))->assertForbidden();

    $this->postJson(tasksUrl($family->id, "/{$task->id}/reopen"))
        ->assertOk()->assertJsonPath('data.status', 'open')->assertJsonPath('data.completed_at', null);
});

it('reorders tasks the user may edit', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $family = Spaces::family($owner, [[$viewer, SpaceRole::Viewer]]);
    [$a, $b, $c] = array_map(fn ($t) => Content::task($family, $owner, $t), ['A', 'B', 'C']);
    $version = $a->updated_at->format('Uv');

    $this->actingAs($owner)->postJson(tasksUrl($family->id, '/reorder'), ['ids' => [$c->id, $a->id, $b->id]])->assertNoContent();
    expect([$c->fresh()->sort_order, $a->fresh()->sort_order, $b->fresh()->sort_order])->toBe([0, 1, 2])
        ->and($a->fresh()->updated_at->format('Uv'))->toBe($version);

    $this->getJson(tasksUrl($family->id))->assertJsonPath('data.*.title', ['C', 'A', 'B']);

    $this->postJson(tasksUrl($family->id, '/reorder'), ['ids' => [$a->id, '01m7s000000000000000000000']])->assertNotFound();
    $this->actingAs($viewer)->postJson(tasksUrl($family->id, '/reorder'), ['ids' => [$a->id]])->assertForbidden();
});

it('supports Idempotency-Key and If-Match on tasks', function () {
    $user = User::factory()->create();
    $space = Spaces::personalOf($user);
    $key = (string) Str::uuid();

    $first = $this->actingAs($user)->withHeader('Idempotency-Key', $key)->postJson(tasksUrl($space->id), ['title' => 'Once']);
    $this->withHeader('Idempotency-Key', $key)->postJson(tasksUrl($space->id), ['title' => 'Once'])
        ->assertHeader('Idempotent-Replayed', 'true')->assertJsonPath('data.id', $first->json('data.id'));
    expect(Task::query()->count())->toBe(1);

    $url = tasksUrl($space->id, '/'.$first->json('data.id'));
    $this->flushHeaders();
    $this->travel(1)->seconds();
    $this->patchJson($url, ['title' => 'Changed'])->assertOk();
    $this->withHeader('If-Match', $first->json('data.version'))->patchJson($url, ['title' => 'Stale'])
        ->assertConflict()->assertJson(['code' => 'version_conflict']);
});
