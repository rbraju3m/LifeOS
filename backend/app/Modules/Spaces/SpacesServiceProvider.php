<?php

namespace App\Modules\Spaces;

use App\Modules\Identity\Events\UserRegistered;
use App\Modules\Shared\Providers\ModuleServiceProvider;
use App\Modules\Spaces\Authorization\SpaceAccess;
use App\Modules\Spaces\Authorization\SpacePolicy;
use App\Modules\Spaces\Listeners\CreatePersonalSpace;
use App\Modules\Spaces\Models\Space;
use App\Modules\Spaces\Models\SpaceMember;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

class SpacesServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        // scoped, not singleton: reset per request and per queue job, so a
        // long-running worker never answers from another job's memo.
        $this->app->scoped(SpaceAccess::class);
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(Space::class, SpacePolicy::class);
        Event::listen(UserRegistered::class, CreatePersonalSpace::class);

        // Membership changes must never be answered from a stale memo.
        $flush = fn () => $this->app->make(SpaceAccess::class)->flush();
        SpaceMember::saved($flush);
        SpaceMember::deleted($flush);
        Space::deleted($flush);
        Space::restored($flush);
    }
}
