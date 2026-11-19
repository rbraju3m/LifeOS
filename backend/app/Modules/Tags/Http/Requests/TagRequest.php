<?php

namespace App\Modules\Tags\Http\Requests;

use App\Modules\Shared\Http\Requests\StrictFormRequest;
use App\Modules\Spaces\Models\Space;
use App\Modules\Tags\Models\Tag;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Create (POST) and update (PATCH) of a tag. On update every field is optional.
 * Name uniqueness is per space and follows the column collation
 * (case- and accent-insensitive).
 *
 * Authorization runs before validation, so a caller who may not write here
 * gets 404/403 and never a validation answer about another space's tags.
 */
class TagRequest extends StrictFormRequest
{
    public function authorize(): bool
    {
        $tag = $this->route('tag');

        if ($tag instanceof Tag) {
            Gate::authorize('update', $tag);
        } else {
            Gate::authorize('create', [Tag::class, $this->route('space')]);
        }

        return true;
    }

    public function rules(): array
    {
        $space = $this->route('space');
        $tag = $this->route('tag');
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [
                $required, 'string', 'min:1', 'max:50',
                Rule::unique('tags', 'name')
                    ->where('space_id', $space instanceof Space ? $space->id : '')
                    ->ignore($tag instanceof Tag ? $tag->id : null),
            ],
            'color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }
}
