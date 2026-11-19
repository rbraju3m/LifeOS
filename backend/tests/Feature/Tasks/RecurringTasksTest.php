<?php

use App\Modules\Identity\Models\User;
use App\Modules\Spaces\Enums\SpaceRole;
use App\Modules\Tasks\Models\Task;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\Content;
use Tests\Support\Spaces;

uses(RefreshDatabase::class);

beforeEach(fn () => Carbon::setTestNow('2026-11-20 06:00:00')); // 12:00 in Dhaka

function recurringTask(User $user, string $spaceId, array $body)
{
    return test()->actingAs($user)->postJson("/api/v1/spaces/{$spaceId}/tasks", ['title' => 'Water plants', ...$body]);
}

function completeTask(string $spaceId, string $taskId)
{
    return test()->postJson("/api/v1/spaces/{$spaceId}/tasks/{$taskId}/complete");
}

it('stores a canonical rule and a series id', function () {
    $user = User::factory()->create();
    $space = Spaces::personalOf($user);

    recurringTask($user, $space->id, ['due_date' => '2026-11-20', 'rrule' => 'rrule:freq=weekly;byday=fr;interval=1'])
        ->assertCreated()
        ->assertJsonPath('data.rrule', 'FREQ=WEEKLY;INTERVAL=1;BYDAY=FR')
        ->assertJsonPath('data.recurrence_series_id', fn ($id) => is_string($id) && strlen($id) === 26);
});

it('validates rules', function (array $body, string $field) {
    $user = User::factory()->create();

    recurringTask($user, Spaces::personalOf($user)->id, $body)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'no due date' => [['rrule' => 'FREQ=DAILY'], 'rrule'],
    'hourly' => [['due_date' => '2026-11-20', 'rrule' => 'FREQ=HOURLY'], 'rrule'],
    'garbage' => [['due_date' => '2026-11-20', 'rrule' => 'every day'], 'rrule'],
    'already ended' => [['due_date' => '2026-11-20', 'rrule' => 'FREQ=DAILY;UNTIL=20261120'], 'rrule'],
]);

it('creates the next instance on completion, copying fields, tags and local time', function () {
    $user = User::factory()->create(['timezone' => 'Asia/Dhaka']);
    $space = Spaces::personalOf($user);
    $list = Content::taskList($space, $user, 'Home');
    $tag = Content::tag($space, $user, 'Garden');

    $id = recurringTask($user, $space->id, [
        'due_date' => '2026-11-20', 'due_time' => '18:00', 'rrule' => 'FREQ=DAILY;COUNT=3',
        'priority' => 2, 'task_list_id' => $list->id, 'tag_ids' => [$tag->id], 'description' => 'Balcony',
    ])->json('data.id');

    $response = completeTask($space->id, $id)->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('meta.next.status', 'open')
        ->assertJsonPath('meta.next.due_date', '2026-11-21')
        ->assertJsonPath('meta.next.due_time', '18:00')
        ->assertJsonPath('meta.next.due_at', '2026-11-21T12:00:00.000000Z')
        ->assertJsonPath('meta.next.rrule', 'FREQ=DAILY;COUNT=2')
        ->assertJsonPath('meta.next.priority', 2)
        ->assertJsonPath('meta.next.task_list_id', $list->id)
        ->assertJsonPath('meta.next.description', 'Balcony')
        ->assertJsonPath('meta.next.tags.0.id', $tag->id);

    expect($response->json('meta.next.recurrence_series_id'))->toBe($response->json('data.recurrence_series_id'));
});

it('ends the series when COUNT runs out', function () {
    $user = User::factory()->create();
    $space = Spaces::personalOf($user);
    $id = recurringTask($user, $space->id, ['due_date' => '2026-11-20', 'rrule' => 'FREQ=DAILY;COUNT=2'])->json('data.id');

    $second = completeTask($space->id, $id)->json('meta.next.id');
    completeTask($space->id, $second)->assertOk()->assertJsonPath('meta.next', null);

    expect(Task::query()->count())->toBe(2);
});

it('never creates two instances for the same date', function () {
    $user = User::factory()->create();
    $space = Spaces::personalOf($user);
    $id = recurringTask($user, $space->id, ['due_date' => '2026-11-20', 'rrule' => 'FREQ=DAILY'])->json('data.id');

    $next = completeTask($space->id, $id)->json('meta.next.id');
    completeTask($space->id, $id)->assertJsonPath('meta.next.id', $next);
    $this->postJson("/api/v1/spaces/{$space->id}/tasks/{$id}/reopen")->assertOk();
    completeTask($space->id, $id)->assertJsonPath('meta.next.id', $next);

    expect(Task::query()->count())->toBe(2);
});

it('does not bring back a next instance the user put in the Trash', function () {
    $user = User::factory()->create();
    $space = Spaces::personalOf($user);
    $id = recurringTask($user, $space->id, ['due_date' => '2026-11-20', 'rrule' => 'FREQ=DAILY'])->json('data.id');
    $next = completeTask($space->id, $id)->json('meta.next.id');
    $this->deleteJson("/api/v1/spaces/{$space->id}/tasks/{$next}");
    $this->postJson("/api/v1/spaces/{$space->id}/tasks/{$id}/reopen");

    completeTask($space->id, $id)->assertJsonPath('meta.next', null);
    expect(Task::withTrashed()->count())->toBe(2);
});

it('skips ahead to today when a task is completed late', function () {
    $user = User::factory()->create(['timezone' => 'Asia/Dhaka']);
    $space = Spaces::personalOf($user);
    $id = recurringTask($user, $space->id, ['due_date' => '2026-11-01', 'rrule' => 'FREQ=DAILY;COUNT=30'])->json('data.id');

    completeTask($space->id, $id)
        ->assertJsonPath('meta.next.due_date', '2026-11-20')
        ->assertJsonPath('meta.next.rrule', 'FREQ=DAILY;COUNT=11');
});

it('keeps the local time across a DST change', function () {
    $user = User::factory()->create();
    $space = Spaces::personalOf($user);
    Carbon::setTestNow('2026-10-30 12:00:00');
    $id = recurringTask($user, $space->id, [
        'due_date' => '2026-10-31', 'due_time' => '09:00', 'timezone' => 'America/New_York', 'rrule' => 'FREQ=WEEKLY',
    ])->assertJsonPath('data.due_at', '2026-10-31T13:00:00.000000Z')->json('data.id');

    completeTask($space->id, $id)
        ->assertJsonPath('meta.next.due_date', '2026-11-07')
        ->assertJsonPath('meta.next.due_time', '09:00')
        ->assertJsonPath('meta.next.due_at', '2026-11-07T14:00:00.000000Z');
});

it('keeps a private series private and lets the assignee generate the next instance', function () {
    $owner = User::factory()->create();
    $child = User::factory()->create();
    $family = Spaces::family($owner, [[$child, SpaceRole::Child]]);

    $private = recurringTask($owner, $family->id, ['due_date' => '2026-11-20', 'rrule' => 'FREQ=DAILY', 'visibility' => 'private'])->json('data.id');
    completeTask($family->id, $private)->assertJsonPath('meta.next.visibility', 'private')->assertJsonPath('meta.next.created_by', $owner->id);

    $chore = recurringTask($owner, $family->id, ['due_date' => '2026-11-20', 'rrule' => 'FREQ=WEEKLY', 'assignee_user_id' => $child->id])->json('data.id');
    $this->actingAs($child);
    completeTask($family->id, $chore)->assertOk()
        ->assertJsonPath('meta.next.assignee_user_id', $child->id)
        ->assertJsonPath('meta.next.created_by', $owner->id);
});

it('sets and clears the rule with PATCH', function () {
    $user = User::factory()->create();
    $space = Spaces::personalOf($user);
    $id = recurringTask($user, $space->id, ['due_date' => '2026-11-20'])->assertJsonPath('data.rrule', null)->json('data.id');
    $url = "/api/v1/spaces/{$space->id}/tasks/{$id}";

    $series = $this->patchJson($url, ['rrule' => 'FREQ=MONTHLY'])->assertOk()->json('data.recurrence_series_id');
    expect($series)->not->toBeNull();

    $this->patchJson($url, ['due_date' => null])->assertJsonValidationErrors('due_date');
    $this->patchJson($url, ['rrule' => null])->assertOk()->assertJsonPath('data.rrule', null)
        ->assertJsonPath('data.recurrence_series_id', $series);
    completeTask($space->id, $id)->assertJsonPath('meta.next', null);
});

it('enforces one instance per series and date in the database', function () {
    $user = User::factory()->create();
    $space = Spaces::personalOf($user);
    $attrs = ['due_date' => '2026-11-20', 'rrule' => 'FREQ=DAILY', 'recurrence_series_id' => '01m7s000000000000000000000'];
    Content::task($space, $user, 'A', attributes: $attrs);

    expect(fn () => DB::transaction(fn () => Content::task($space, $user, 'B', attributes: $attrs)))
        ->toThrow(UniqueConstraintViolationException::class);
});
