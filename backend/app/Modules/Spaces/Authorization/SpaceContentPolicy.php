<?php

namespace App\Modules\Spaces\Authorization;

use App\Modules\Identity\Models\User;
use App\Modules\Spaces\Contracts\SpaceOwnedContent;
use App\Modules\Spaces\Enums\Visibility;
use App\Modules\Spaces\Models\Space;
use Illuminate\Auth\Access\Response;

/**
 * Base policy for space content (docs/blueprint/13 "Authorization").
 *
 * Anything the user may not even see is a 404 (denyAsNotFound), so the API
 * never confirms that another user's item exists. Visible-but-not-allowed
 * actions are a 403.
 */
abstract class SpaceContentPolicy
{
    public function __construct(protected readonly SpaceAccess $access) {}

    /** The RoleMatrix content type, e.g. 'task'. */
    abstract protected function contentType(): string;

    public function view(User $user, SpaceOwnedContent $item): Response
    {
        return $this->canSee($user, $item) ? Response::allow() : Response::denyAsNotFound();
    }

    public function create(User $user, Space $space): Response
    {
        if (! $this->access->isMember($user, $space)) {
            return Response::denyAsNotFound();
        }

        return $this->access->can($user, $space, 'content.create', $this->contentType())
            ? Response::allow()
            : Response::denyWithStatus(403);
    }

    public function update(User $user, SpaceOwnedContent $item): Response
    {
        return $this->ownOrAny($user, $item, 'content.edit_own', 'content.edit_any');
    }

    public function delete(User $user, SpaceOwnedContent $item): Response
    {
        return $this->ownOrAny($user, $item, 'content.delete_own', 'content.delete_any');
    }

    public function restore(User $user, SpaceOwnedContent $item): Response
    {
        return $this->delete($user, $item);
    }

    protected function canSee(User $user, SpaceOwnedContent $item): bool
    {
        if (! $this->access->can($user, $item->spaceId(), 'content.view', $this->contentType())) {
            return false;
        }

        return $item->visibility() === Visibility::Space || $item->creatorId() === $user->id;
    }

    protected function ownOrAny(User $user, SpaceOwnedContent $item, string $ownAbility, string $anyAbility): Response
    {
        if (! $this->canSee($user, $item)) {
            return Response::denyAsNotFound();
        }

        $type = $this->contentType();
        $isCreator = $item->creatorId() === $user->id;

        return $this->access->can($user, $item->spaceId(), $anyAbility, $type)
            || ($isCreator && $this->access->can($user, $item->spaceId(), $ownAbility, $type))
            ? Response::allow()
            : Response::denyWithStatus(403);
    }
}
