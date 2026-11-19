<?php

namespace App\Modules\Audit\Http\Controllers;

use App\Modules\Audit\Http\Resources\SecurityEventResource;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Identity\Models\User;
use App\Modules\Shared\Http\Pagination\PerPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** GET /me/security-events: the signed-in user's own auth.* audit entries, newest first. */
class SecurityEventsController
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $events = AuditLog::query()
            ->where('actor_user_id', $user->getKey())
            ->security()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(PerPage::fromRequest($request));

        return SecurityEventResource::collection($events);
    }
}
