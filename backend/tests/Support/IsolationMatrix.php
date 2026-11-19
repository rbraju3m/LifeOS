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
     * @return array<string, array{method: string, item: string, body?: array<string, mixed>, expect: array<string, int>}>
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
