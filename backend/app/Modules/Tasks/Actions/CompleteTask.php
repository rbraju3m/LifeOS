<?php

namespace App\Modules\Tasks\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Shared\Recurrence\RecurrenceCalculator;
use App\Modules\Shared\Recurrence\RRule;
use App\Modules\Shared\ValueObjects\LocalDateTime;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Support\DueFields;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Completing and reopening (docs/blueprint/10 §10.4).
 *
 * Both are idempotent. Completing a recurring task creates its next instance
 * in the same transaction (complete-to-generate): the first date after this
 * one that is also on or after today in the task's timezone, at the same
 * local time. A series never gets two instances for one date (unique index),
 * so completing again after a reopen does not create a duplicate. Reopening
 * leaves an already created next instance in place.
 */
class CompleteTask
{
    public function __construct(private readonly RecurrenceCalculator $calculator) {}

    /**
     * @return array{task: Task, next: Task|null}
     */
    public function complete(Task $task, User $user): array
    {
        return DB::transaction(function () use ($task, $user): array {
            $locked = Task::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== TaskStatus::Completed) {
                $locked->forceFill([
                    'status' => TaskStatus::Completed,
                    'completed_at' => now(),
                    'completed_by' => $user->id,
                ])->save();
            }

            return ['task' => $locked->refresh(), 'next' => $this->nextInstance($locked)];
        });
    }

    public function reopen(Task $task): Task
    {
        if ($task->status !== TaskStatus::Open) {
            $task->forceFill(['status' => TaskStatus::Open, 'completed_at' => null, 'completed_by' => null])->save();
        }

        return $task->refresh();
    }

    private function nextInstance(Task $task): ?Task
    {
        if ($task->rrule === null || $task->due_date === null || $task->recurrence_series_id === null) {
            return null;
        }

        $today = CarbonImmutable::now($task->timezone)->toDateString();
        $next = $this->calculator->next(RRule::parse($task->rrule), $task->due_date->format('Y-m-d'), $today);

        if ($next === null) {
            return null;
        }

        $existing = $this->existing($task->recurrence_series_id, $next['date']);
        if ($existing !== null) {
            return $existing->trashed() ? null : $existing;
        }

        $time = DueFields::localTime($task);
        $instance = new Task;
        $instance->forceFill([
            'space_id' => $task->space_id,
            'created_by' => $task->created_by,
            'visibility' => $task->visibility,
            'task_list_id' => $task->task_list_id,
            'assignee_user_id' => $task->assignee_user_id,
            'title' => $task->title,
            'description' => $task->description,
            'priority' => $task->priority,
            'sort_order' => $task->sort_order,
            'timezone' => $task->timezone,
            'due_date' => $next['date'],
            'due_at' => $time !== null ? (new LocalDateTime("{$next['date']} {$time}", $task->timezone))->toUtc() : null,
            'rrule' => ($next['rule'] ?? RRule::parse($task->rrule))->toString(),
            'recurrence_series_id' => $task->recurrence_series_id,
        ]);

        try {
            DB::transaction(fn () => $instance->save()); // savepoint: a lost race must not abort the outer transaction
        } catch (UniqueConstraintViolationException) {
            $existing = $this->existing($task->recurrence_series_id, $next['date']);

            return $existing !== null && ! $existing->trashed() ? $existing : null;
        }

        $instance->syncTagIds($task->tagIds());

        return $instance->refresh();
    }

    private function existing(string $seriesId, string $date): ?Task
    {
        return Task::withTrashed()->where('recurrence_series_id', $seriesId)->where('due_date', $date)->first();
    }
}
