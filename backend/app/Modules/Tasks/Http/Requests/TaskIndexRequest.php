<?php

namespace App\Modules\Tasks\Http\Requests;

use App\Modules\Tasks\Support\TaskQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Query parameters of GET /spaces/{space}/tasks. Unknown filter keys are a 422. */
class TaskIndexRequest extends FormRequest
{
    private const FILTERS = ['status', 'list_id', 'tag', 'assignee', 'due_from', 'due_to', 'view', 'trashed'];

    public function authorize(): bool
    {
        Gate::authorize('view', $this->route('space'));

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $sortKey = '-?('.implode('|', array_keys(TaskQuery::SORTS)).')';

        return [
            'filter' => ['sometimes', 'array'],
            'filter.status' => ['sometimes', Rule::in(['open', 'completed', 'cancelled'])],
            'filter.list_id' => ['sometimes', 'string', 'size:26'],
            'filter.tag' => ['sometimes', 'string', 'size:26'],
            'filter.assignee' => ['sometimes', Rule::in(['me'])],
            'filter.due_from' => ['sometimes', 'date_format:Y-m-d'],
            'filter.due_to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:filter.due_from'],
            'filter.view' => ['sometimes', Rule::in(TaskQuery::VIEWS)],
            'filter.trashed' => ['sometimes', Rule::in(['only'])],
            'sort' => ['sometimes', 'string', "regex:/^{$sortKey}(,{$sortKey}){0,2}$/"],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $filter = $this->query('filter');
            foreach (is_array($filter) ? array_keys($filter) : [] as $key) {
                if (! in_array($key, self::FILTERS, true)) {
                    $validator->errors()->add("filter.{$key}", __('validation.prohibited', ['attribute' => "filter[{$key}]"]));
                }
            }
        }];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $filter = $this->validated('filter', []);

        return is_array($filter) ? $filter : [];
    }
}
