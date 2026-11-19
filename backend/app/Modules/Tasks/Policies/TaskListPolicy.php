<?php

namespace App\Modules\Tasks\Policies;

use App\Modules\Spaces\Authorization\SpaceContentPolicy;

class TaskListPolicy extends SpaceContentPolicy
{
    protected function contentType(): string
    {
        return 'task_list';
    }
}
