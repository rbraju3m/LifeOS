<?php

namespace App\Modules\Spaces\Routing;

use App\Modules\Spaces\Contracts\SpaceOwnedContent;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps route parameter names to space-owned content models, so nested routes
 * like /spaces/{space}/tags/{tag} can use Route::scopeBindings() without the
 * Spaces module knowing about feature modules (docs/blueprint/09 §9.1 rule 2).
 *
 * Content modules register in their service provider:
 *   SpaceContentRegistry::register('tag', Tag::class);
 *
 * A registered child is then looked up only inside the parent space, so an
 * ID from another space is a 404.
 */
final class SpaceContentRegistry
{
    /** @var array<string, class-string<Model&SpaceOwnedContent>> */
    private static array $models = [];

    /**
     * @param  class-string<Model&SpaceOwnedContent>  $model
     */
    public static function register(string $parameter, string $model): void
    {
        self::$models[$parameter] = $model;
    }

    /**
     * @return class-string<Model&SpaceOwnedContent>|null
     */
    public static function modelFor(string $parameter): ?string
    {
        return self::$models[$parameter] ?? null;
    }
}
