<?php

namespace App\Modules\Tasks\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Spaces\Authorization\SpaceContentPolicy;
use App\Modules\Tasks\Models\Task;
use Illuminate\Auth\Access\Response;

class TaskPolicy extends SpaceContentPolicy
{
    protected function contentType(): string
    {
        return 'task';
    }

    /** Anyone who may edit the task, or its assignee (task.complete_assigned). */
    public function complete(User $user, Task $task): Response
    {
        $update = $this->update($user, $task);

        if ($update->allowed() || $update->status() === 404) {
            return $update;
        }

        return $task->assignee_user_id === $user->id
            && $this->access->can($user, $task->space_id, 'task.complete_assigned')
            ? Response::allow()
            : $update;
    }
}
