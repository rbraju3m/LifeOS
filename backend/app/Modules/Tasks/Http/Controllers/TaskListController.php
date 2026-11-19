<?php

namespace App\Modules\Tasks\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Shared\Http\Pagination\PerPage;
use App\Modules\Spaces\Models\Space;
use App\Modules\Tasks\Actions\SaveTaskList;
use App\Modules\Tasks\Actions\TrashTasks;
use App\Modules\Tasks\Http\Requests\TaskListRequest;
use App\Modules\Tasks\Http\Resources\TaskListResource;
use App\Modules\Tasks\Models\TaskList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/** /spaces/{space}/task-lists (docs/blueprint/12 §12.5). */
class TaskListController
{
    public function index(Request $request, Space $space): AnonymousResourceCollection
    {
        Gate::authorize('view', $space);
        $request->validate([
            'filter' => ['sometimes', 'array:archived,trashed'],
            'filter.archived' => ['sometimes', 'in:0,1'],
            'filter.trashed' => ['sometimes', 'in:only'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $query = TaskList::query()->visibleTo($user)->where('space_id', $space->id);
        $request->input('filter.archived') === '1' ? $query->whereNotNull('archived_at') : $query->whereNull('archived_at');
        if ($request->input('filter.trashed') === 'only') {
            $query->onlyTrashed();
        }

        return TaskListResource::collection(
            $query->orderBy('sort_order')->orderBy('id')->cursorPaginate(PerPage::fromRequest($request)),
        );
    }

    public function store(TaskListRequest $request, Space $space, SaveTaskList $save): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return (new TaskListResource($save->create($space, $user, $request->validated())))->response()->setStatusCode(201);
    }

    public function show(Space $space, TaskList $taskList): TaskListResource
    {
        Gate::authorize('view', $taskList);

        return new TaskListResource($taskList);
    }

    public function update(TaskListRequest $request, Space $space, TaskList $taskList, SaveTaskList $save): TaskListResource
    {
        /** @var User $user */
        $user = $request->user();

        return new TaskListResource($save->update($request, $taskList, $user, $request->validated()));
    }

    public function archive(Space $space, TaskList $taskList, SaveTaskList $save): TaskListResource
    {
        Gate::authorize('update', $taskList);

        return new TaskListResource($save->archive($taskList, true));
    }

    public function unarchive(Space $space, TaskList $taskList, SaveTaskList $save): TaskListResource
    {
        Gate::authorize('update', $taskList);

        return new TaskListResource($save->archive($taskList, false));
    }

    public function destroy(Request $request, Space $space, TaskList $taskList, TrashTasks $trash): Response
    {
        Gate::authorize('delete', $taskList);

        /** @var User $user */
        $user = $request->user();
        $trash->trashList($taskList, $user);

        return response()->noContent();
    }

    public function restore(Space $space, TaskList $taskList, TrashTasks $trash): TaskListResource
    {
        Gate::authorize('restore', $taskList);

        return new TaskListResource($trash->restoreList($taskList));
    }

    public function forceDestroy(Space $space, TaskList $taskList, TrashTasks $trash): Response
    {
        Gate::authorize('delete', $taskList);
        $trash->forceDeleteList($taskList);

        return response()->noContent();
    }
}
