<?php

namespace App\Modules\Spaces\Listeners;

use App\Modules\Identity\Events\UserRegistered;
use App\Modules\Spaces\Enums\SpaceRole;
use App\Modules\Spaces\Enums\SpaceType;
use App\Modules\Spaces\Models\Space;
use App\Modules\Spaces\Models\SpaceMember;

/**
 * Every user gets exactly one personal space, created in the registration
 * transaction. Nobody else can ever be a member of it.
 */
class CreatePersonalSpace
{
    public function handle(UserRegistered $event): void
    {
        $user = $event->user;

        $space = new Space([
            'name' => __('spaces.personal_name', [], $user->locale),
            'timezone' => $user->timezone,
            'currency' => $user->currency,
        ]);
        $space->forceFill(['type' => SpaceType::Personal, 'owner_user_id' => $user->id])->save();

        $member = new SpaceMember;
        $member->forceFill([
            'space_id' => $space->id,
            'user_id' => $user->id,
            'role' => SpaceRole::Owner,
            'joined_at' => now(),
        ])->save();
    }
}
