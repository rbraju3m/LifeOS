<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Shared\Http\Requests\StrictFormRequest;

class ForgotPasswordRequest extends StrictFormRequest
{
    public function rules(): array
    {
        return ['email' => ['required', 'string', 'max:254', 'email:rfc']];
    }
}
