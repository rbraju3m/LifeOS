<?php

use App\Modules\Identity\Events\UserRegistered;
use App\Modules\Identity\Models\User;
use App\Modules\Spaces\Enums\SpaceRole;
use App\Modules\Spaces\Enums\SpaceType;
use App\Modules\Spaces\Models\Space;
use App\Modules\Spaces\Models\SpaceMember;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Spaces;

uses(RefreshDatabase::class);

it('creates a personal space with the new user as its only member, owner', function () {
    Notification::fake();

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Arif Hasan', 'email' => 'arif@example.com', 'locale' => 'bn', 'timezone' => 'Asia/Dhaka',
        'password' => 'a long enough passphrase', 'password_confirmation' => 'a long enough passphrase',
        'accept_terms' => true,
    ])->assertStatus(202);

    $user = User::query()->sole();
    $space = Spaces::personalOf($user);

    expect($space->type)->toBe(SpaceType::Personal)
        ->and($space->name)->toBe('ব্যক্তিগত')
        ->and($space->timezone)->toBe('Asia/Dhaka')
        ->and($space->members()->count())->toBe(1)
        ->and($space->members()->sole()->role)->toBe(SpaceRole::Owner);
});

it('rolls back the whole registration if the personal space cannot be created', function () {
    Notification::fake();
    Event::listen(fn (UserRegistered $e) => throw new RuntimeException('boom'));

    expect(fn () => $this->withoutExceptionHandling()->postJson('/api/v1/auth/register', [
        'name' => 'Arif Hasan', 'email' => 'arif@example.com',
        'password' => 'a long enough passphrase', 'password_confirmation' => 'a long enough passphrase',
        'accept_terms' => true,
    ]))->toThrow(RuntimeException::class);

    expect(User::query()->count())->toBe(0)
        ->and(Space::query()->count())->toBe(0);
});

it('allows only one personal space per user, enforced by the database', function () {
    $user = User::factory()->create();

    $second = new Space(['name' => 'Another']);
    $second->forceFill(['type' => SpaceType::Personal, 'owner_user_id' => $user->id]);

    expect(fn () => $second->save())->toThrow(UniqueConstraintViolationException::class);
});

it('lets a user own several family spaces', function () {
    $user = User::factory()->create();

    Spaces::family($user);
    Spaces::family($user);

    expect(Space::query()->where('owner_user_id', $user->id)->count())->toBe(3);
});

it('does not allow mass-assigning ownership or membership', function (Closure $assign) {
    // Strict mode (outside production) throws; production silently discards.
    expect($assign)->toThrow(MassAssignmentException::class);
})->with([
    'space type/owner' => [fn () => new Space(['name' => 'X', 'type' => 'family', 'owner_user_id' => 'someone'])],
    'member role' => [fn () => new SpaceMember(['role' => 'owner'])],
    'member user' => [fn () => new SpaceMember(['user_id' => 'someone'])],
]);
