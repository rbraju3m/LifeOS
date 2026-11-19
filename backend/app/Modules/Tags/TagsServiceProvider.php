<?php

namespace App\Modules\Tags;

use App\Modules\Shared\Providers\ModuleServiceProvider;
use App\Modules\Spaces\Routing\SpaceContentRegistry;
use App\Modules\Tags\Models\Tag;
use App\Modules\Tags\Policies\TagPolicy;
use Illuminate\Support\Facades\Gate;

class TagsServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        Gate::policy(Tag::class, TagPolicy::class);
        SpaceContentRegistry::register('tag', Tag::class);
    }
}
