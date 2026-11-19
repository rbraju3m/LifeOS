<?php

namespace Tests\Support;

use App\Modules\Identity\Models\User;
use App\Modules\Spaces\Enums\MemberStatus;
use App\Modules\Spaces\Enums\SpaceRole;
use App\Modules\Spaces\Models\Space;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * The authorization isolation matrix (docs/blueprint/19, threat I1).
 *
 * Every API route that takes an ID is listed in ROUTES. IsolationMatrixTest
 * calls each one as every actor below against an item owned by "alice" and
 * checks the status. A route-discovery test fails when a route with an ID
 * parameter is missing from this list (or from EXEMPT, with a reason).
 *
 * Actors (the item belongs to alice, the owner of a family space):
 *   stranger         not a member of the space                       → 404
 *   removed_member   was a member, now removed                       → 404
 *   guest            not signed in                                   → 401
 *   member_private   family member; alice marked the item private    → 404
 *   member_personal  family member; the item is in alice's personal space → 404
 *   viewer, child    family members with those roles: per route
 *
 * Adding a content resource: add its routes here with a `make` closure that
 * creates the item, and the expected viewer/child statuses.
 */
final class IsolationMatrix
{
    public const ACTORS = ['stranger', 'removed_member', 'guest', 'member_private', 'member_personal', 'viewer', 'child'];

    private const DEFAULTS = [
        'stranger' => 404,
        'removed_member' => 404,
        'guest' => 401,
        'member_private' => 404,
        'member_personal' => 404,
    ];

    /** Routes with ID parameters that are deliberately not in the matrix. */
    public const EXEMPT = [
        // Signed URL bound to the signed-in user's own id; covered by EmailVerificationTest.
        'verification.verify',
    ];

    /**
     * `body` may be a closure receiving the route parameters. `trashed` puts the
     * item in the Trash first (for restore and permanent-delete routes).
     *
     * @return array<string, array{method: string, item: string, body?: array<string, mixed>|Closure(array<string, Model>): array<string, mixed>, trashed?: bool, expect: array<string, int>}>
     */
    public static function routes(): array
    {
        return [
            'spaces.show' => ['method' => 'GET', 'item' => 'space', 'expect' => ['viewer' => 200, 'child' => 200]],
            'spaces.entitlements.show' => ['method' => 'GET', 'item' => 'space', 'expect' => ['viewer' => 200, 'child' => 200]],

            'tags.index' => ['method' => 'GET', 'item' => 'space', 'expect' => ['viewer' => 200, 'child' => 200]],
            'tags.store' => ['method' => 'POST', 'item' => 'space', 'body' => ['name' => 'Isolation'], 'expect' => ['viewer' => 403, 'child' => 403]],
            'tags.show' => ['method' => 'GET', 'item' => 'tag', 'expect' => ['viewer' => 200, 'child' => 200]],
            'tags.update' => ['method' => 'PATCH', 'item' => 'tag', 'body' => ['name' => 'Renamed'], 'expect' => ['viewer' => 403, 'child' => 403]],
            'tags.destroy' => ['method' => 'DELETE', 'item' => 'tag', 'expect' => ['viewer' => 403, 'child' => 403]],

            'task-lists.index' => ['method' => 'GET', 'item' => 'space', 'expect' => ['viewer' => 200, 'child' => 200]],
            'task-lists.store' => ['method' => 'POST', 'item' => 'space', 'body' => ['name' => 'Isolation'], 'expect' => ['viewer' => 403, 'child' => 403]],
            'task-lists.show' => ['method' => 'GET', 'item' => 'task_list', 'expect' => ['viewer' => 200, 'child' => 200]],
            'task-lists.update' => ['method' => 'PATCH', 'item' => 'task_list', 'body' => ['name' => 'Renamed'], 'expect' => ['viewer' => 403, 'child' => 403]],
            'task-lists.destroy' => ['method' => 'DELETE', 'item' => 'task_list', 'expect' => ['viewer' => 403, 'child' => 403]],
            'task-lists.archive' => ['method' => 'POST', 'item' => 'task_list', 'expect' => ['viewer' => 403, 'child' => 403]],
            'task-lists.unarchive' => ['method' => 'POST', 'item' => 'task_list', 'expect' => ['viewer' => 403, 'child' => 403]],
            'task-lists.restore' => ['method' => 'POST', 'item' => 'task_list', 'trashed' => true, 'expect' => ['viewer' => 403, 'child' => 403]],
            'task-lists.force-destroy' => ['method' => 'DELETE', 'item' => 'task_list', 'trashed' => true, 'expect' => ['viewer' => 403, 'child' => 403]],

            'tasks.index' => ['method' => 'GET', 'item' => 'space', 'expect' => ['viewer' => 200, 'child' => 200]],
            'tasks.store' => ['method' => 'POST', 'item' => 'space', 'body' => ['title' => 'Isolation'], 'expect' => ['viewer' => 403, 'child' => 201]],
            'tasks.reorder' => ['method' => 'POST', 'item' => 'task', 'body' => fn (array $p) => ['ids' => [$p['task']->getKey()]], 'expect' => ['viewer' => 403, 'child' => 403]],
            'tasks.show' => ['method' => 'GET', 'item' => 'task', 'expect' => ['viewer' => 200, 'child' => 200]],
            'tasks.update' => ['method' => 'PATCH', 'item' => 'task', 'body' => ['title' => 'Renamed'], 'expect' => ['viewer' => 403, 'child' => 403]],
            'tasks.destroy' => ['method' => 'DELETE', 'item' => 'task', 'expect' => ['viewer' => 403, 'child' => 403]],
            'tasks.complete' => ['method' => 'POST', 'item' => 'task', 'expect' => ['viewer' => 403, 'child' => 403]],
            'tasks.reopen' => ['method' => 'POST', 'item' => 'task', 'expect' => ['viewer' => 403, 'child' => 403]],
            'tasks.restore' => ['method' => 'POST', 'item' => 'task', 'trashed' => true, 'expect' => ['viewer' => 403, 'child' => 403]],
            'tasks.force-destroy' => ['method' => 'DELETE', 'item' => 'task', 'trashed' => true, 'expect' => ['viewer' => 403, 'child' => 403]],
        ];
    }

    /**
     * How to create each item type in a space, as $creator. Returns the route
     * parameters, or null when the item type cannot be private.
     *
     * @return array<string, Closure(Space, User, bool): (array<string, Model>|null)>
     */
    public static function makers(): array
    {
        return [
            'space' => fn (Space $space, User $creator, bool $private) => $private ? null : ['space' => $space],
            'tag' => function (Space $space, User $creator, bool $private) {
                if ($private) {
                    return null; // tags are always space-wide
                }

                return ['space' => $space, 'tag' => Content::tag($space, $creator, 'Isolation item')];
            },
            'task' => fn (Space $space, User $creator, bool $private) => [
                'space' => $space,
                'task' => Content::task($space, $creator, 'Isolation item', $private),
            ],
            'task_list' => fn (Space $space, User $creator, bool $private) => [
                'space' => $space,
                'taskList' => Content::taskList($space, $creator, 'Isolation item', $private),
            ],
        ];
    }

    public static function expected(string $route, string $actor): int
    {
        $expect = self::routes()[$route]['expect'] + self::DEFAULTS;

        return $expect[$actor];
    }

    /**
     * Builds the cast and the item for one case. Returns null when the case
     * does not apply (e.g. member_private for an item type with no privacy).
     *
     * @return array{actor: User|null, params: array<string, Model>}|null
     */
    public static function arrange(string $route, string $actor): ?array
    {
        $alice = User::factory()->create();
        $member = User::factory()->create();
        $viewer = User::factory()->create();
        $child = User::factory()->create();
        $removed = User::factory()->create();
        $stranger = User::factory()->create();

        $family = Spaces::family($alice, [
            [$member, SpaceRole::Member],
            [$viewer, SpaceRole::Viewer],
            [$child, SpaceRole::Child],
        ]);
        Spaces::add($family, $removed, SpaceRole::Member, MemberStatus::Removed);

        $maker = self::makers()[self::routes()[$route]['item']];

        $params = match ($actor) {
            'member_private' => $maker($family, $alice, true),
            'member_personal' => $maker(Spaces::personalOf($alice), $alice, false),
            default => $maker($family, $alice, false),
        };

        if ($params === null) {
            return null;
        }

        if (self::routes()[$route]['trashed'] ?? false) {
            foreach ($params as $name => $model) {
                if ($name !== 'space' && method_exists($model, 'delete')) {
                    $model->delete();
                }
            }
        }

        return [
            'actor' => match ($actor) {
                'stranger' => $stranger,
                'removed_member' => $removed,
                'guest' => null,
                'member_private', 'member_personal' => $member,
                'viewer' => $viewer,
                'child' => $child,
            },
            'params' => $params,
        ];
    }
}
