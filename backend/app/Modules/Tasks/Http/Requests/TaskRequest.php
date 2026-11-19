<?php

namespace App\Modules\Tasks\Http\Requests;

use App\Modules\Identity\Models\User;
use App\Modules\Shared\Http\Requests\StrictFormRequest;
use App\Modules\Shared\Recurrence\InvalidRRule;
use App\Modules\Shared\Recurrence\RecurrenceCalculator;
use App\Modules\Shared\Recurrence\RRule;
use App\Modules\Spaces\Authorization\SpaceAccess;
use App\Modules\Spaces\Models\Space;
use App\Modules\Tags\Contracts\TagDirectory;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskList;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create (POST) or update (PATCH) a task (docs/blueprint/12 §12.5).
 *
 * Authorized before validation. Cross-references are checked against the
 * space in the URL: the list must be visible to the caller in that space,
 * tags must belong to it, and the assignee must be an active member (and,
 * for a private task, its creator).
 */
class TaskRequest extends StrictFormRequest
{
    public const MAX_TAGS = 20;

    public function authorize(): bool
    {
        $task = $this->route('task');

        if ($task instanceof Task) {
            Gate::authorize('update', $task);
        } else {
            Gate::authorize('create', [Task::class, $this->route('space')]);
        }

        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'min:1', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'priority' => ['sometimes', 'integer', 'between:0,3'],
            'sort_order' => ['sometimes', 'integer', 'between:-1000000,1000000'],
            'visibility' => ['sometimes', Rule::in(['space', 'private'])],
            'task_list_id' => ['sometimes', 'nullable', 'string', 'size:26'],
            'assignee_user_id' => ['sometimes', 'nullable', 'string', 'size:26'],
            'tag_ids' => ['sometimes', 'array', 'max:'.self::MAX_TAGS],
            'tag_ids.*' => ['string', 'size:26', 'distinct'],
            'due_date' => ['nullable', 'date_format:Y-m-d', 'required_with:due_time'], // no 'sometimes': it would skip required_with
            'due_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'timezone' => ['sometimes', 'string', 'timezone:all'],
            'rrule' => ['sometimes', 'nullable', 'string', 'max:'.RRule::MAX_LENGTH],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [...parent::after(), fn (Validator $v) => $this->checkReferences($v)];
    }

    /**
     * A rule must parse, needs a due date (sent now or already on the task),
     * and must have at least one date after it.
     */
    private function checkRecurrence(Validator $validator): void
    {
        $task = $this->route('task');
        $rule = $this->has('rrule') ? $this->input('rrule') : ($task instanceof Task ? $task->rrule : null);

        if (! is_string($rule)) {
            return;
        }

        $dueDate = $this->has('due_date') ? $this->input('due_date') : ($task instanceof Task ? $task->due_date?->format('Y-m-d') : null);
        if (! is_string($dueDate)) {
            $validator->errors()->add($this->has('rrule') ? 'rrule' : 'due_date', __('tasks.rrule_required_due_date'));

            return;
        }

        if (! $this->has('rrule')) {
            return; // the stored rule was valid when it was saved
        }

        try {
            $parsed = RRule::parse($rule);
        } catch (InvalidRRule $e) {
            $validator->errors()->add('rrule', __('tasks.rrule.'.$e->reason));

            return;
        }

        if (! app(RecurrenceCalculator::class)->hasNext($parsed, $dueDate)) {
            $validator->errors()->add('rrule', __('tasks.rrule_ended'));
        }
    }

    private function checkReferences(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $this->checkRecurrence($validator);

        /** @var Space $space */
        $space = $this->route('space');
        /** @var User $user */
        $user = $this->user();
        $task = $this->route('task');

        $listId = $this->input('task_list_id');
        if (is_string($listId)) {
            $visible = TaskList::query()->visibleTo($user)
                ->where('space_id', $space->id)->whereKey($listId)->exists();
            if (! $visible) {
                $validator->errors()->add('task_list_id', __('validation.exists', ['attribute' => 'task list']));
            }
        }

        $tagIds = $this->input('tag_ids');
        if (is_array($tagIds) && $tagIds !== []) {
            $tagIds = array_values(array_filter($tagIds, 'is_string'));
            $found = app(TagDirectory::class)->existingInSpace($space->id, $tagIds);
            if (count($found) !== count($tagIds)) {
                $validator->errors()->add('tag_ids', __('validation.exists', ['attribute' => 'tag']));
            }
        }

        $assigneeId = $this->has('assignee_user_id')
            ? $this->input('assignee_user_id')
            : ($task instanceof Task ? $task->assignee_user_id : null);

        if (is_string($assigneeId)) {
            $assignee = User::query()->find($assigneeId);
            if ($assignee === null || ! app(SpaceAccess::class)->isMember($assignee, $space)) {
                $validator->errors()->add('assignee_user_id', __('validation.exists', ['attribute' => 'assignee']));

                return;
            }

            $visibility = $this->input('visibility', $task instanceof Task ? $task->visibility->value : 'space');
            $creatorId = $task instanceof Task ? $task->created_by : $user->id;
            if ($visibility === 'private' && $assigneeId !== $creatorId) {
                $validator->errors()->add('assignee_user_id', __('tasks.private_assignee'));
            }
        }
    }
}
