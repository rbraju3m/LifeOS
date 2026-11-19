<?php

use App\Modules\Identity\Models\User;
use App\Modules\Spaces\Enums\SpaceRole;
use App\Modules\Tasks\Enums\TaskStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\Content;
use Tests\Support\Spaces;

uses(RefreshDatabase::class);

function titles(string $spaceId, string $query): array
{
    return test()->getJson("/api/v1/spaces/{$spaceId}/tasks?{$query}")->assertOk()->json('data.*.title');
}

it('finds today, overdue and upcoming tasks in the viewer timezone', function () {
    // 2026-11-20 20:30 UTC is already 2026-11-21 02:30 in Dhaka (UTC+6).
    Carbon::setTestNow('2026-11-20 20:30:00');
    $user = User::factory()->create(['timezone' => 'Asia/Dhaka']);
    $space = Spaces::personalOf($user);
    $make = fn (string $title, array $attrs) => Content::task($space, $user, $title, attributes: $attrs);

    $make('today date-only', ['due_date' => '2026-11-21']);
    $make('yesterday date-only', ['due_date' => '2026-11-20']);
    $make('today, time passed', ['due_date' => '2026-11-21', 'due_at' => '2026-11-20 20:00:00']);
    $make('tomorrow', ['due_date' => '2026-11-22']);
    $make('done yesterday', ['due_date' => '2026-11-20', 'status' => TaskStatus::Completed]);
    $make('undated', []);

    $this->actingAs($user);
    expect(titles($space->id, 'filter[view]=today&sort=created_at'))->toBe(['today date-only', 'today, time passed'])
        ->and(titles($space->id, 'filter[view]=overdue&sort=created_at'))->toBe(['yesterday date-only', 'today, time passed'])
        ->and(titles($space->id, 'filter[view]=upcoming'))->toBe(['tomorrow']);
});

it('filters by status, list, tag, assignee and due range', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $space = Spaces::family($owner, [[$member, SpaceRole::Member]]);
    $list = Content::taskList($space, $owner, 'L');
    $tag = Content::tag($space, $owner, 'Tg');
    $tagged = Content::task($space, $owner, 'tagged', attributes: ['due_date' => '2026-12-10']);
    DB::table('task_tag')->insert(['task_id' => $tagged->id, 'tag_id' => $tag->id]);
    Content::task($space, $owner, 'in list', attributes: ['task_list_id' => $list->id, 'due_date' => '2026-12-01']);
    Content::task($space, $owner, 'assigned', attributes: ['assignee_user_id' => $member->id]);
    Content::task($space, $owner, 'done', attributes: ['status' => TaskStatus::Completed]);

    $this->actingAs($member);
    expect(titles($space->id, 'filter[status]=completed'))->toBe(['done'])
        ->and(titles($space->id, "filter[list_id]={$list->id}"))->toBe(['in list'])
        ->and(titles($space->id, "filter[tag]={$tag->id}"))->toBe(['tagged'])
        ->and(titles($space->id, 'filter[assignee]=me'))->toBe(['assigned'])
        ->and(titles($space->id, 'filter[due_from]=2026-12-05&filter[due_to]=2026-12-31'))->toBe(['tagged']);

    $this->getJson("/api/v1/spaces/{$space->id}/tasks?filter[owner]=x")->assertJsonValidationErrors('filter.owner');
    $this->getJson("/api/v1/spaces/{$space->id}/tasks?sort=title")->assertJsonValidationErrors('sort');
});

it('never lists other members\' private tasks, even to owners', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $space = Spaces::family($owner, [[$member, SpaceRole::Member]]);
    Content::task($space, $member, 'secret', true);
    Content::task($space, $member, 'shared');

    $this->actingAs($owner);
    expect(titles($space->id, ''))->toBe(['shared']);
});

it('sorts by due with undated tasks last and pages through them with a cursor', function () {
    $user = User::factory()->create(['timezone' => 'UTC']);
    $space = Spaces::personalOf($user);
    $make = fn (string $title, array $attrs = []) => Content::task($space, $user, $title, attributes: $attrs);
    $make('undated 1');
    $make('dec 2 09:00', ['due_date' => '2026-12-02', 'due_at' => '2026-12-02 09:00:00']);
    $make('dec 1', ['due_date' => '2026-12-01']);
    $make('undated 2');
    $make('dec 2 date-only', ['due_date' => '2026-12-02']);

    $this->actingAs($user);
    $all = [];
    $cursor = '';
    do {
        $page = $this->getJson("/api/v1/spaces/{$space->id}/tasks?sort=due&per_page=2&cursor={$cursor}")->assertOk();
        $all = [...$all, ...$page->json('data.*.title')];
        $cursor = $page->json('meta.next_cursor');
    } while ($cursor !== null);

    expect($all)->toBe(['dec 1', 'dec 2 09:00', 'dec 2 date-only', 'undated 1', 'undated 2']);
});

it('sorts by several keys, mixing directions', function () {
    $user = User::factory()->create();
    $space = Spaces::personalOf($user);
    foreach ([['a', 1], ['b', 3], ['c', 3], ['d', 0]] as [$title, $priority]) {
        $this->travel(1)->seconds();
        Content::task($space, $user, $title, attributes: ['priority' => $priority]);
    }

    $this->actingAs($user);
    expect(titles($space->id, 'sort=-priority,created_at&per_page=3'))->toBe(['b', 'c', 'a']);

    $all = [];
    $cursor = '';
    do {
        $page = $this->getJson("/api/v1/spaces/{$space->id}/tasks?sort=-priority,created_at&per_page=1&cursor={$cursor}")->assertOk();
        $all = [...$all, ...$page->json('data.*.title')];
        $cursor = $page->json('meta.next_cursor');
    } while ($cursor !== null && count($all) < 10);

    expect($all)->toBe(['b', 'c', 'a', 'd']);
});
