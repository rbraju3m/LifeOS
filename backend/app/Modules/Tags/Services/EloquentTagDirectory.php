<?php

namespace App\Modules\Tags\Services;

use App\Modules\Tags\Contracts\TagDirectory;
use App\Modules\Tags\Contracts\TagSummary;
use App\Modules\Tags\Models\Tag;

final class EloquentTagDirectory implements TagDirectory
{
    public function existingInSpace(string $spaceId, array $tagIds): array
    {
        if ($tagIds === []) {
            return [];
        }

        /** @var list<string> $ids */
        $ids = Tag::query()->where('space_id', $spaceId)->whereKey($tagIds)->pluck('id')->all();

        return $ids;
    }

    public function summaries(array $tagIds): array
    {
        if ($tagIds === []) {
            return [];
        }

        return Tag::query()->whereKey(array_values(array_unique($tagIds)))->orderBy('name')->get()
            ->mapWithKeys(fn (Tag $tag) => [$tag->id => new TagSummary($tag->id, $tag->name, $tag->color)])
            ->all();
    }
}
