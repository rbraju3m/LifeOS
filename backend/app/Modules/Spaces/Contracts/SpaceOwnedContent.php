<?php

namespace App\Modules\Spaces\Contracts;

use App\Modules\Spaces\Enums\Visibility;

/**
 * Implemented by every content model that lives in a space (tasks, notes,
 * events, records, …), usually through the BelongsToSpace trait.
 */
interface SpaceOwnedContent
{
    public function spaceId(): string;

    public function creatorId(): ?string;

    public function visibility(): Visibility;

    /** The content type key used by RoleMatrix, e.g. 'task'. */
    public static function contentType(): string;
}
