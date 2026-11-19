<?php

namespace App\Modules\Spaces\Authorization;

use App\Modules\Identity\Models\User;
use App\Modules\Spaces\Enums\MemberStatus;
use App\Modules\Spaces\Enums\SpaceRole;
use App\Modules\Spaces\Models\Space;
use App\Modules\Spaces\Models\SpaceMember;

/**
 * Answers "is this user an active member of this space, and with which role?".
 *
 * Results are memoised per request / queue job (the binding is scoped), and
 * the memo is cleared whenever a membership row changes in this process
 * (see SpacesServiceProvider).
 */
class SpaceAccess
{
    /** @var array<string, SpaceRole|null> */
    private array $roles = [];

    public function role(User $user, Space|string $space): ?SpaceRole
    {
        $spaceId = $space instanceof Space ? $space->id : $space;
        $key = $user->id.'|'.$spaceId;

        if (! array_key_exists($key, $this->roles)) {
            $member = SpaceMember::query()
                ->where('space_id', $spaceId)
                ->where('user_id', $user->id)
                ->where('status', MemberStatus::Active)
                ->whereHas('space')
                ->first();

            $this->roles[$key] = $member?->role;
        }

        return $this->roles[$key];
    }

    public function isMember(User $user, Space|string $space): bool
    {
        return $this->role($user, $space) !== null;
    }

    public function can(User $user, Space|string $space, string $ability, ?string $contentType = null): bool
    {
        $role = $this->role($user, $space);

        return $role !== null && RoleMatrix::allows($role, $ability, $contentType);
    }

    public function flush(): void
    {
        $this->roles = [];
    }
}
