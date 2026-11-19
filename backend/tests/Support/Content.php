<?php

namespace Tests\Support;

use App\Modules\Identity\Models\User;
use App\Modules\Spaces\Models\Space;
use App\Modules\Tags\Models\Tag;

/** Creates content rows directly (no factories for module models yet). */
final class Content
{
    public static function tag(Space $space, User $creator, string $name, ?string $color = null): Tag
    {
        $tag = new Tag(['name' => $name, 'color' => $color]);
        $tag->forceFill(['space_id' => $space->id, 'created_by' => $creator->id])->save();

        return $tag;
    }
}
