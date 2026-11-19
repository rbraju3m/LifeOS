<?php

namespace App\Modules\Tags\Contracts;

/**
 * How feature modules use tags without querying the Tags models
 * (docs/blueprint/09 §9.1 rule 3). Each feature keeps its own pivot table.
 */
interface TagDirectory
{
    /**
     * The given tag IDs that exist in the space (others are dropped).
     *
     * @param  list<string>  $tagIds
     * @return list<string>
     */
    public function existingInSpace(string $spaceId, array $tagIds): array;

    /**
     * Summaries keyed by tag ID; unknown IDs are left out.
     *
     * @param  list<string>  $tagIds
     * @return array<string, TagSummary>
     */
    public function summaries(array $tagIds): array;
}
