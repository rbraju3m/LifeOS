<?php

namespace App\Modules\Spaces\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Spaces\Enums\MemberStatus;
use App\Modules\Spaces\Http\Resources\SpaceResource;
use App\Modules\Spaces\Models\Space;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class SpaceController
{
    /** Spaces the user is an active member of, personal space first. */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $spaces = Space::query()
            ->whereHas('members', fn ($q) => $q->where('user_id', $user->id)->where('status', MemberStatus::Active))
            ->orderByRaw("type = 'personal' desc")
            ->orderBy('name')
            ->get();

        return SpaceResource::collection($spaces);
    }

    public function show(Space $space): SpaceResource
    {
        Gate::authorize('view', $space);

        return new SpaceResource($space);
    }
}
