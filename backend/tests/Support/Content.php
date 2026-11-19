<?php

namespace Tests\Support;

use App\Modules\Identity\Models\User;
use App\Modules\Spaces\Enums\Visibility;
use App\Modules\Spaces\Models\Space;
use App\Modules\Tags\Models\Tag;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskList;

/** Creates content rows directly (no factories for module models yet). */
final class Content
{
    public static function tag(Space $space, User $creator, string $name, ?string $color = null): Tag
    {
        $tag = new Tag(['name' => $name, 'color' => $color]);
        $tag->forceFill(['space_id' => $space->id, 'created_by' => $creator->id])->save();

        return $tag;
    }

    /**
     * @param  array<string, mixed>  $attributes  any column, including server-owned ones
     */
    public static function task(Space $space, User $creator, string $title, bool $private = false, array $attributes = []): Task
    {
        $task = new Task;
        $task->forceFill([
            'title' => $title,
            'space_id' => $space->id,
            'created_by' => $creator->id,
            'visibility' => $private ? Visibility::Private : Visibility::Space,
            'timezone' => $creator->timezone,
            ...$attributes,
        ])->save();

        return $task->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function taskList(Space $space, User $creator, string $name, bool $private = false, array $attributes = []): TaskList
    {
        $list = new TaskList;
        $list->forceFill([
            'name' => $name,
            'space_id' => $space->id,
            'created_by' => $creator->id,
            'visibility' => $private ? Visibility::Private : Visibility::Space,
            ...$attributes,
        ])->save();

        return $list->refresh();
    }
}
