<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Http\Middleware\SetLocale;
use App\Modules\Shared\Http\Requests\StrictFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends StrictFormRequest
{
    protected array $alsoAllowed = ['password_confirmation'];

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            // No `unique` rule: an existing email must get the same response (no enumeration).
            'email' => ['required', 'string', 'max:254', app()->isProduction() ? 'email:rfc,dns' : 'email:rfc'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'locale' => ['sometimes', 'nullable', Rule::in(SetLocale::SUPPORTED)],
            'timezone' => ['sometimes', 'nullable', 'timezone:all_with_bc'],
            'accept_terms' => ['accepted'],
        ];
    }
}
