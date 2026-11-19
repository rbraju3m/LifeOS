<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Actions\RegisterUser;
use App\Modules\Identity\Http\Requests\RegisterRequest;
use Illuminate\Http\JsonResponse;

class RegisterController
{
    /**
     * Always 202 with the same body, whether or not the email was new.
     * The user then signs in; email verification can happen later.
     */
    public function __invoke(RegisterRequest $request, RegisterUser $registerUser): JsonResponse
    {
        /** @var array{name: string, email: string, password: string, locale?: string|null, timezone?: string|null} $input */
        $input = $request->safe()->only(['name', 'email', 'password', 'locale', 'timezone']);

        $registerUser->handle($input, $request->ip());

        return new JsonResponse(['message' => __('identity.registration_received')], 202);
    }
}
