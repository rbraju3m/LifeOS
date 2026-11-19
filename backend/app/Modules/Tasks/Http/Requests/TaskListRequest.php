<?php

namespace App\Modules\Tasks\Http\Requests;

use App\Modules\Shared\Http\Requests\StrictFormRequest;
use App\Modules\Tasks\Models\TaskList;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Create (POST) or update (PATCH) a task list. Authorized before validation. */
class TaskListRequest extends StrictFormRequest
{
    public function authorize(): bool
    {
        $list = $this->route('taskList');

        if ($list instanceof TaskList) {
            Gate::authorize('update', $list);
        } else {
            Gate::authorize('create', [TaskList::class, $this->route('space')]);
        }

        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'min:1', 'max:100'],
            'color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:32', 'regex:/^[a-z0-9-]+$/'],
            'sort_order' => ['sometimes', 'integer', 'between:-1000000,1000000'],
            'visibility' => ['sometimes', Rule::in(['space', 'private'])],
        ];
    }
}
