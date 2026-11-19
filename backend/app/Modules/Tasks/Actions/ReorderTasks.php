<?php

namespace App\Modules\Tasks\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Spaces\Models\Space;
use App\Modules\Tasks\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Sets sort_order to each task's position in $ids. Every ID must be a task
 * in the space that the user can see (otherwise 404, revealing nothing) and
 * may edit (otherwise 403). Ordering is not a content change, so updated_at
 * and the version stay the same.
 */
class ReorderTasks
{
    /**
     * @param  list<string>  $ids
     */
    public function handle(Space $space, User $user, array $ids): void
    {
        $tasks = Task::query()->visibleTo($user)->where('space_id', $space->id)->whereKey($ids)->get()->keyBy('id');

        if ($tasks->count() !== count($ids)) {
            throw new NotFoundHttpException;
        }

        foreach ($tasks as $task) {
            Gate::authorize('update', $task);
        }

        DB::transaction(function () use ($ids): void {
            foreach ($ids as $position => $id) {
                Task::query()->whereKey($id)->toBase()->update(['sort_order' => $position]);
            }
        });
    }
}
