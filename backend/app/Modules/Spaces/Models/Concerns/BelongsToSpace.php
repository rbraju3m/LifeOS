<?php

namespace App\Modules\Spaces\Models\Concerns;

use App\Modules\Identity\Models\User;
use App\Modules\Spaces\Enums\MemberStatus;
use App\Modules\Spaces\Enums\Visibility;
use App\Modules\Spaces\Models\Space;
use App\Modules\Spaces\Models\SpaceMember;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For content models with the [OWN] columns: space_id, created_by, visibility
 * (docs/blueprint/10 §10.0). Pair with the SpaceOwnedContent interface.
 *
 * Every list query must go through visibleTo(): it returns only rows in
 * spaces where the user is an active member, and never other members'
 * private rows, whatever their role.
 *
 * Content without a `visibility` column (e.g. tags, which are always
 * space-wide) overrides hasVisibilityColumn() to return false.
 */
trait BelongsToSpace
{
    public function initializeBelongsToSpace(): void
    {
        if ($this->hasVisibilityColumn()) {
            $this->mergeCasts(['visibility' => Visibility::class]);
        }
    }

    public function hasVisibilityColumn(): bool
    {
        return true;
    }

    /**
     * @return BelongsTo<Space, $this>
     */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereIn($query->qualifyColumn('space_id'), SpaceMember::query()
            ->select('space_id')
            ->where('user_id', $user->id)
            ->where('status', MemberStatus::Active));

        if (! $this->hasVisibilityColumn()) {
            return;
        }

        $query->where(fn (Builder $q) => $q
            ->where($query->qualifyColumn('visibility'), Visibility::Space)
            ->orWhere($query->qualifyColumn('created_by'), $user->id));
    }

    public function spaceId(): string
    {
        return (string) $this->getAttribute('space_id');
    }

    public function creatorId(): ?string
    {
        $creator = $this->getAttribute('created_by');

        return is_string($creator) ? $creator : null;
    }

    public function visibility(): Visibility
    {
        if (! $this->hasVisibilityColumn()) {
            return Visibility::Space;
        }

        $visibility = $this->getAttribute('visibility');

        return $visibility instanceof Visibility ? $visibility : Visibility::from((string) $visibility);
    }
}
