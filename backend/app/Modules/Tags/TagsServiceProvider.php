<?php

namespace App\Modules\Tags;

use App\Modules\Shared\Providers\ModuleServiceProvider;
use App\Modules\Spaces\Routing\SpaceContentRegistry;
use App\Modules\Tags\Contracts\TagDirectory;
use App\Modules\Tags\Models\Tag;
use App\Modules\Tags\Policies\TagPolicy;
use App\Modules\Tags\Services\EloquentTagDirectory;
use Illuminate\Support\Facades\Gate;

class TagsServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TagDirectory::class, EloquentTagDirectory::class);
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(Tag::class, TagPolicy::class);
        SpaceContentRegistry::register('tag', Tag::class);
    }
}
