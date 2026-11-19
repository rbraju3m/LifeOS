<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Http\Middleware\SetLocale;
use App\Modules\Identity\Models\User;
use App\Modules\Shared\Http\Requests\StrictFormRequest;
use Illuminate\Validation\Rule;

class UpdateMeRequest extends StrictFormRequest
{
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:120'],
            'phone_e164' => ['sometimes', 'nullable', 'string', 'regex:/^\+[1-9]\d{6,14}$/', Rule::unique('users', 'phone_e164')->ignore($user->id)],
            'locale' => ['sometimes', Rule::in(SetLocale::SUPPORTED)],
            'timezone' => ['sometimes', 'timezone:all_with_bc'],
            'country_code' => ['sometimes', 'string', 'regex:/^[A-Z]{2}$/'],
            'currency' => ['sometimes', 'string', 'regex:/^[A-Z]{3}$/'],
        ];
    }
}
