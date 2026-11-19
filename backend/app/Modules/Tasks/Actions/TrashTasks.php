<?php

namespace App\Modules\Tasks\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Shared\Http\Problems\ApiProblemException;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskList;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Trash, restore and permanent deletion for tasks and lists
 * (docs/blueprint/10 §10.10: Trash for 30 days, then purged).
 *
 * Deleting a list moves its tasks that the deleter can see to the Trash with
 * the same deleted_at, so restoring the list brings back exactly those tasks
 * (tasks trashed on their own earlier stay in the Trash). Other members'
 * private tasks in the list are not trashed by someone who cannot see them;
 * they are moved out of the list instead.
 */
class TrashTasks
{
    public function trashTask(Task $task): void
    {
        $task->delete();
    }

    public function restoreTask(Task $task): Task
    {
        return DB::transaction(function () use ($task): Task {
            if ($task->trashed()) {
                $task->restore();
            }

            // Its list is still in the Trash: keep the task, outside the list.
            if ($task->task_list_id !== null && ! TaskList::query()->whereKey($task->task_list_id)->exists()) {
                $task->forceFill(['task_list_id' => null])->save();
            }

            return $task->refresh();
        });
    }

    public function forceDeleteTask(Task $task): void
    {
        $this->assertTrashed($task->trashed());
        $task->forceDelete();
    }

    public function trashList(TaskList $list, User $deleter): void
    {
        DB::transaction(function () use ($list, $deleter): void {
            // Millisecond precision, written identically to the list and its
            // tasks so restoreList() can match them exactly.
            $at = Carbon::now()->format('Y-m-d H:i:s.v');

            $visible = Task::query()->visibleTo($deleter)->where('task_list_id', $list->id)->pluck('id');

            Task::query()->where('task_list_id', $list->id)->whereKeyNot($visible->all())
                ->update(['task_list_id' => null]);
            Task::query()->whereKey($visible->all())->update(['deleted_at' => $at]);

            TaskList::query()->whereKey($list->id)->update(['deleted_at' => $at]);
        });
    }

    public function restoreList(TaskList $list): TaskList
    {
        return DB::transaction(function () use ($list): TaskList {
            $deletedAt = $list->deleted_at;

            if ($deletedAt !== null) {
                Task::onlyTrashed()->where('task_list_id', $list->id)->where('deleted_at', $deletedAt->format('Y-m-d H:i:s.v'))
                    ->update(['deleted_at' => null]);
                $list->restore();
            }

            return $list->refresh();
        });
    }

    public function forceDeleteList(TaskList $list): void
    {
        $this->assertTrashed($list->trashed());
        $list->forceDelete();
    }

    private function assertTrashed(bool $trashed): void
    {
        if (! $trashed) {
            throw new ApiProblemException(409, 'not_in_trash');
        }
    }
}
