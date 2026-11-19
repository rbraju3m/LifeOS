<?php

namespace Tests\Support;

use App\Modules\Identity\Models\User;
use App\Modules\Spaces\Enums\MemberStatus;
use App\Modules\Spaces\Enums\SpaceRole;
use App\Modules\Spaces\Enums\SpaceType;
use App\Modules\Spaces\Models\Space;
use App\Modules\Spaces\Models\SpaceMember;

/** Builds spaces and memberships for tests (no API for family spaces exists yet). */
final class Spaces
{
    public static function personalOf(User $user): Space
    {
        return Space::query()->where('type', SpaceType::Personal)->where('owner_user_id', $user->id)->sole();
    }

    /**
     * @param  array<int, array{0: User, 1: SpaceRole}>  $members
     */
    public static function family(User $owner, array $members = []): Space
    {
        $space = new Space(['name' => 'Family']);
        $space->forceFill(['type' => SpaceType::Family, 'owner_user_id' => $owner->id])->save();
        self::add($space, $owner, SpaceRole::Owner);

        foreach ($members as [$user, $role]) {
            self::add($space, $user, $role);
        }

        return $space;
    }

    public static function add(Space $space, User $user, SpaceRole $role, MemberStatus $status = MemberStatus::Active): SpaceMember
    {
        $member = new SpaceMember;
        $member->forceFill([
            'space_id' => $space->id, 'user_id' => $user->id, 'role' => $role,
            'status' => $status, 'joined_at' => now(),
        ])->save();

        return $member;
    }
}
