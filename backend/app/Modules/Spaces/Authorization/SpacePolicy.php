<?php

namespace App\Modules\Spaces\Authorization;

use App\Modules\Identity\Models\User;
use App\Modules\Spaces\Models\Space;
use Illuminate\Auth\Access\Response;

class SpacePolicy
{
    public function __construct(private readonly SpaceAccess $access) {}

    /** Members see a space; to everyone else it does not exist. */
    public function view(User $user, Space $space): Response
    {
        return $this->access->isMember($user, $space) ? Response::allow() : Response::denyAsNotFound();
    }
}
