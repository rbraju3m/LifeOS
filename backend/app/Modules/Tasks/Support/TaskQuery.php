<?php

namespace App\Modules\Tasks\Support;

use App\Modules\Identity\Models\User;
use App\Modules\Spaces\Models\Space;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Filters and sorting for GET /spaces/{space}/tasks (docs/blueprint/12 §12.1, §12.5).
 *
 * Views use the viewer's own timezone for "today":
 *   today     open tasks due today
 *   overdue   open tasks whose due time has passed (date-only: due before today)
 *   upcoming  open tasks due after today
 */
final class TaskQuery
{
    /**
     * Public sort key => column. `id` is always the final tie-breaker.
     *
     * Never cursor-paginate on a datetime-cast column: the cursor stores it
     * as 'Y-m-d H:i:s' and loses the milliseconds, so rows repeat or are
     * skipped. "created_at" therefore sorts by the ULID id, which is ordered
     * by creation time to the millisecond; due_sort has no cast.
     */
    public const SORTS = [
        'due' => 'due_sort',
        'priority' => 'priority',
        'created_at' => 'id',
        'sort_order' => 'sort_order',
    ];

    public const VIEWS = ['today', 'overdue', 'upcoming'];

    /**
     * @param  array<string, mixed>  $filter  validated filter[...] values
     * @return Builder<Task>
     */
    public static function build(Space $space, User $user, array $filter, ?string $sort): Builder
    {
        $query = Task::query()->visibleTo($user)->where('tasks.space_id', $space->id);

        if (($filter['trashed'] ?? null) === 'only') {
            $query->onlyTrashed();
        }

        if (isset($filter['status'])) {
            $query->where('status', $filter['status']);
        }
        if (isset($filter['list_id'])) {
            $query->where('task_list_id', $filter['list_id']);
        }
        if (isset($filter['tag'])) {
            $query->whereExists(fn (QueryBuilder $q) => $q->select(DB::raw(1))->from('task_tag')
                ->whereColumn('task_tag.task_id', 'tasks.id')->where('task_tag.tag_id', $filter['tag']));
        }
        if (($filter['assignee'] ?? null) === 'me') {
            $query->where('assignee_user_id', $user->id);
        }
        if (isset($filter['due_from'])) {
            $query->where('due_date', '>=', $filter['due_from']);
        }
        if (isset($filter['due_to'])) {
            $query->where('due_date', '<=', $filter['due_to']);
        }
        if (isset($filter['view']) && is_string($filter['view'])) {
            self::applyView($query, $filter['view'], $user);
        }

        foreach (self::parseSort($sort) as [$column, $direction]) {
            $query->orderBy($column, $direction);
        }

        return $query;
    }

    /**
     * @return list<array{0: string, 1: 'asc'|'desc'}>
     */
    public static function parseSort(?string $sort): array
    {
        $orders = [];
        $lastDirection = 'asc';

        foreach (array_filter(explode(',', $sort ?? 'sort_order')) as $key) {
            $direction = str_starts_with($key, '-') ? 'desc' : 'asc';
            $orders[] = [self::SORTS[ltrim($key, '-')], $direction];
            $lastDirection = $direction;
        }

        if (end($orders) === false || end($orders)[0] !== 'id') {
            $orders[] = ['id', $lastDirection];
        }

        return $orders;
    }

    /**
     * @param  Builder<Task>  $query
     */
    private static function applyView(Builder $query, string $view, User $user): void
    {
        $now = CarbonImmutable::now();
        $today = $now->setTimezone($user->timezone)->toDateString();

        $query->where('status', TaskStatus::Open);

        match ($view) {
            'today' => $query->where('due_date', $today),
            'upcoming' => $query->where('due_date', '>', $today),
            'overdue' => $query->where(fn (Builder $q) => $q
                ->where(fn (Builder $timed) => $timed->whereNotNull('due_at')->where('due_at', '<', $now->format('Y-m-d H:i:s.v')))
                ->orWhere(fn (Builder $dated) => $dated->whereNull('due_at')->where('due_date', '<', $today))),
            default => null,
        };
    }
}
