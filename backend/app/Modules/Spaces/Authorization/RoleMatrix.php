<?php

namespace App\Modules\Spaces\Authorization;

use App\Modules\Spaces\Enums\SpaceRole;
use InvalidArgumentException;

/**
 * What each space role may do (docs/blueprint/14-family-and-permission-model.md).
 *
 * A role maps to `true` (allowed for every content type) or to the list of
 * content types it is allowed for. Roles not listed are denied. Changing this
 * table is a security change: update the blueprint and RoleMatrixTest too.
 */
final class RoleMatrix
{
    private const ALL = ['owner', 'admin', 'member', 'child', 'viewer'];

    /** @var array<string, array<string, true|list<string>>> */
    private const MATRIX = [
        'content.view' => ['owner' => true, 'admin' => true, 'member' => true, 'child' => true, 'viewer' => true],
        'content.create' => ['owner' => true, 'admin' => true, 'member' => true, 'child' => ['task', 'note']],
        'content.edit_own' => ['owner' => true, 'admin' => true, 'member' => true, 'child' => true],
        'content.delete_own' => ['owner' => true, 'admin' => true, 'member' => true, 'child' => true],
        'content.edit_any' => ['owner' => true, 'admin' => true, 'member' => ['task', 'task_list', 'event']],
        'content.delete_any' => ['owner' => true, 'admin' => true],
        'task.complete_assigned' => ['owner' => true, 'admin' => true, 'member' => true, 'child' => true],
        'records.view' => ['owner' => true, 'admin' => true, 'member' => true, 'viewer' => true],
        'records.reveal_secret_own' => ['owner' => true, 'admin' => true, 'member' => true],
        'records.reveal_secret_any' => ['owner' => true, 'admin' => true],
        'members.invite' => ['owner' => true, 'admin' => true],
        'members.manage' => ['owner' => true, 'admin' => true],
        'space.settings' => ['owner' => true, 'admin' => true],
        'space.delete' => ['owner' => true],
        'space.transfer' => ['owner' => true],
        'space.billing' => ['owner' => true],
        'audit.view_space' => ['owner' => true, 'admin' => true],
    ];

    public static function allows(SpaceRole $role, string $ability, ?string $contentType = null): bool
    {
        $rule = self::MATRIX[$ability] ?? throw new InvalidArgumentException("Unknown space ability [{$ability}].");
        $grant = $rule[$role->value] ?? false;

        if ($grant === true) {
            return true;
        }

        return is_array($grant) && $contentType !== null && in_array($contentType, $grant, true);
    }

    /**
     * @return list<string>
     */
    public static function abilities(): array
    {
        return array_keys(self::MATRIX);
    }

    /**
     * @return list<string>
     */
    public static function roles(): array
    {
        return self::ALL;
    }
}
