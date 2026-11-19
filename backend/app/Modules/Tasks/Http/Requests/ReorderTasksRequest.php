<?php

namespace App\Modules\Tasks\Http\Requests;

use App\Modules\Shared\Http\Requests\StrictFormRequest;
use Illuminate\Support\Facades\Gate;

class ReorderTasksRequest extends StrictFormRequest
{
    public const MAX = 500;

    public function authorize(): bool
    {
        Gate::authorize('view', $this->route('space'));

        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX],
            'ids.*' => ['string', 'size:26', 'distinct'],
        ];
    }
}
