<?php

namespace App\Modules\Tasks\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Shared\Http\Pagination\PerPage;
use App\Modules\Spaces\Models\Space;
use App\Modules\Tasks\Actions\CompleteTask;
use App\Modules\Tasks\Actions\ReorderTasks;
use App\Modules\Tasks\Actions\SaveTask;
use App\Modules\Tasks\Actions\TrashTasks;
use App\Modules\Tasks\Http\Requests\ReorderTasksRequest;
use App\Modules\Tasks\Http\Requests\TaskIndexRequest;
use App\Modules\Tasks\Http\Requests\TaskRequest;
use App\Modules\Tasks\Http\Resources\TaskResource;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Support\TaskQuery;
use App\Modules\Tasks\Support\TaskTags;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/** /spaces/{space}/tasks (docs/blueprint/12 §12.5). */
class TaskController
{
    public function index(TaskIndexRequest $request, Space $space): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();
        $sort = $request->validated('sort');

        $tasks = TaskQuery::build($space, $user, $request->filters(), is_string($sort) ? $sort : null)
            ->cursorPaginate(PerPage::fromRequest($request));
        TaskTags::load($tasks->items());

        return TaskResource::collection($tasks);
    }

    public function store(TaskRequest $request, Space $space, SaveTask $save): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return (new TaskResource($save->create($space, $user, $request->validated())))->response()->setStatusCode(201);
    }

    public function show(Space $space, Task $task): TaskResource
    {
        Gate::authorize('view', $task);

        return new TaskResource($task);
    }

    public function update(TaskRequest $request, Space $space, Task $task, SaveTask $save): TaskResource
    {
        /** @var User $user */
        $user = $request->user();

        return new TaskResource($save->update($request, $task, $user, $request->validated()));
    }

    /** Returns the completed task, and in meta.next the next instance of a recurring task (or null). */
    public function complete(Request $request, Space $space, Task $task, CompleteTask $complete): TaskResource
    {
        Gate::authorize('complete', $task);

        /** @var User $user */
        $user = $request->user();
        $result = $complete->complete($task, $user);

        return (new TaskResource($result['task']))->additional([
            'meta' => ['next' => $result['next'] !== null ? new TaskResource($result['next']) : null],
        ]);
    }

    public function reopen(Space $space, Task $task, CompleteTask $complete): TaskResource
    {
        Gate::authorize('complete', $task);

        return new TaskResource($complete->reopen($task));
    }

    public function reorder(ReorderTasksRequest $request, Space $space, ReorderTasks $reorder): Response
    {
        /** @var User $user */
        $user = $request->user();
        /** @var list<string> $ids */
        $ids = $request->validated('ids');
        $reorder->handle($space, $user, $ids);

        return response()->noContent();
    }

    public function destroy(Space $space, Task $task, TrashTasks $trash): Response
    {
        Gate::authorize('delete', $task);
        $trash->trashTask($task);

        return response()->noContent();
    }

    public function restore(Space $space, Task $task, TrashTasks $trash): TaskResource
    {
        Gate::authorize('restore', $task);

        return new TaskResource($trash->restoreTask($task));
    }

    public function forceDestroy(Space $space, Task $task, TrashTasks $trash): Response
    {
        Gate::authorize('delete', $task);
        $trash->forceDeleteTask($task);

        return response()->noContent();
    }
}
