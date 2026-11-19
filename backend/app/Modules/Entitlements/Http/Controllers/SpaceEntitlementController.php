<?php

namespace App\Modules\Entitlements\Http\Controllers;

use App\Modules\Entitlements\Entitlements;
use App\Modules\Spaces\Models\Space;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class SpaceEntitlementController
{
    /** What this space's plan allows, and current usage. For display only; enforcement is server-side. */
    public function show(Space $space, Entitlements $entitlements): JsonResponse
    {
        Gate::authorize('view', $space);

        $resolved = $entitlements->for($space);

        return new JsonResponse(['data' => [
            'plan' => $resolved->planCode,
            'features' => $resolved->values,
            'usage' => [
                'storage_used_bytes' => $space->storage_used_bytes,
                'members' => $space->activeMembers()->count(),
            ],
        ]]);
    }
}
