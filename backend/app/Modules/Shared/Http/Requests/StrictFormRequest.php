<?php

namespace App\Modules\Shared\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * A form request that rejects top-level fields it has no rule for, so
 * clients get a clear 422 instead of having fields silently ignored, and
 * server-owned fields (status, created_by, space_id, …) cannot be smuggled in.
 */
abstract class StrictFormRequest extends FormRequest
{
    /**
     * Extra top-level fields that are accepted without a rule of their own,
     * e.g. 'password_confirmation' for a 'confirmed' rule.
     *
     * @var list<string>
     */
    protected array $alsoAllowed = [];

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $allowed = [
                    ...array_map(fn (string $key): string => explode('.', $key)[0], array_keys($this->rules())),
                    ...$this->alsoAllowed,
                ];

                foreach (array_keys($this->all()) as $key) {
                    if (! in_array((string) $key, $allowed, true)) {
                        $validator->errors()->add((string) $key, __('validation.prohibited', ['attribute' => $key]));
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    abstract public function rules(): array;
}
