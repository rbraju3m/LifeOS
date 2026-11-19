<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Http\Requests\LogoutOtherSessionsRequest;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class LogoutOtherSessionsController
{
    public function __invoke(LogoutOtherSessionsRequest $request): Response
    {
        Auth::guard('web')->logoutOtherDevices($request->string('password')->toString());

        return response()->noContent();
    }
}
