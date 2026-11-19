<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Http\Requests\UpdateMeRequest;
use App\Modules\Identity\Http\Requests\UpdatePreferencesRequest;
use App\Modules\Identity\Http\Resources\UserResource;
use App\Modules\Identity\Models\User;
use Illuminate\Http\Request;

class MeController
{
    public function show(Request $request): UserResource
    {
        return new UserResource($this->user($request)->load('preferences'));
    }

    public function update(UpdateMeRequest $request): UserResource
    {
        $user = $this->user($request);
        $user->fill($request->validated());

        if ($user->isDirty('phone_e164')) {
            $user->forceFill(['phone_verified_at' => null]);
        }

        $user->save();

        return new UserResource($user->load('preferences'));
    }

    public function updatePreferences(UpdatePreferencesRequest $request): UserResource
    {
        $user = $this->user($request);
        $user->preferences()->firstOrCreate()->update($request->validated());

        return new UserResource($user->load('preferences'));
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
