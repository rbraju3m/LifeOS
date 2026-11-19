<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Shared\Http\Requests\StrictFormRequest;

class LogoutOtherSessionsRequest extends StrictFormRequest
{
    public function rules(): array
    {
        return ['password' => ['required', 'string', 'current_password:web']];
    }
}
