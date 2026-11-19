<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Http\Requests\ForgotPasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController
{
    /**
     * Always 202 with the same body: whether the email exists, or a link was
     * recently sent, is never revealed.
     */
    public function __invoke(ForgotPasswordRequest $request): JsonResponse
    {
        Password::broker(config('fortify.passwords'))->sendResetLink([
            'email' => mb_strtolower(trim($request->string('email')->toString())),
        ]);

        return new JsonResponse(['message' => __('identity.reset_link_requested')], 202);
    }
}
