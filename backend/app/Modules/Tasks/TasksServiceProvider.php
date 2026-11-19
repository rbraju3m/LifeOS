<?php

namespace App\Modules\Tasks;

use App\Modules\Shared\Providers\ModuleServiceProvider;
use App\Modules\Spaces\Routing\SpaceContentRegistry;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskList;
use App\Modules\Tasks\Policies\TaskListPolicy;
use App\Modules\Tasks\Policies\TaskPolicy;
use Illuminate\Support\Facades\Gate;

class TasksServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(TaskList::class, TaskListPolicy::class);
        SpaceContentRegistry::register('task', Task::class);
        SpaceContentRegistry::register('taskList', TaskList::class);
    }
}
