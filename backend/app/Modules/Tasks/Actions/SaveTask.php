<?php

namespace App\Modules\Tasks\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Shared\Http\Concurrency\Version;
use App\Modules\Shared\Http\Problems\ApiProblemException;
use App\Modules\Shared\Recurrence\RRule;
use App\Modules\Spaces\Models\Space;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Support\DueFields;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates and updates tasks from validated input. Authorization and
 * cross-reference checks happen in TaskRequest; this sets the server-owned
 * columns and keeps the tag pivot in step.
 */
class SaveTask
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Space $space, User $creator, array $data): Task
    {
        return DB::transaction(function () use ($space, $creator, $data): Task {
            $task = new Task;
            $task->fill(Arr::only($data, $task->getFillable()));
            $task->forceFill([
                'space_id' => $space->id,
                'created_by' => $creator->id,
                'visibility' => $data['visibility'] ?? 'space',
                'task_list_id' => $data['task_list_id'] ?? null,
                'assignee_user_id' => $data['assignee_user_id'] ?? null,
                ...DueFields::resolve($data, $creator->timezone),
                ...self::recurrence($data, null),
            ])->save();

            if (isset($data['tag_ids']) && is_array($data['tag_ids'])) {
                /** @var list<string> $tagIds */
                $tagIds = $data['tag_ids'];
                $task->syncTagIds($tagIds);
            }

            return $task->refresh();
        });
    }

    /**
     * The stored rule is canonical. A task joins a series the first time it
     * gets a rule; clearing the rule stops repeating but keeps the series id.
     *
     * @param  array<string, mixed>  $data
     * @return array{rrule?: string|null, recurrence_series_id?: string|null}
     */
    private static function recurrence(array $data, ?Task $current): array
    {
        $rule = $data['rrule'] ?? null;

        if (! is_string($rule)) {
            return array_key_exists('rrule', $data) ? ['rrule' => null] : [];
        }

        return [
            'rrule' => RRule::parse($rule)->toString(),
            'recurrence_series_id' => $current->recurrence_series_id ?? strtolower((string) Str::ulid()),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Request $request, Task $task, User $editor, array $data): Task
    {
        return DB::transaction(function () use ($request, $task, $editor, $data): Task {
            $locked = Task::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
            Version::assertMatches($request, $locked);

            if (isset($data['visibility']) && $data['visibility'] !== $locked->visibility->value
                && $locked->created_by !== $editor->id) {
                throw new ApiProblemException(403, 'forbidden', __('tasks.visibility_creator_only'));
            }

            $locked->fill(Arr::only($data, $locked->getFillable()));

            foreach (['visibility', 'task_list_id', 'assignee_user_id'] as $column) {
                if (array_key_exists($column, $data)) {
                    $locked->forceFill([$column => $data[$column]]);
                }
            }

            if (array_key_exists('rrule', $data)) {
                $locked->forceFill(self::recurrence($data, $locked));
            }

            if (array_intersect(['due_date', 'due_time', 'timezone'], array_keys($data)) !== []) {
                $locked->forceFill(DueFields::resolve($data, $editor->timezone, $locked));
            }

            $locked->save();

            if (array_key_exists('tag_ids', $data) && is_array($data['tag_ids'])) {
                /** @var list<string> $tagIds */
                $tagIds = $data['tag_ids'];
                $locked->syncTagIds($tagIds);
                $locked->touch();
            }

            return $locked->refresh();
        });
    }
}
