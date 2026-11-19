<?php

use App\Modules\Audit\AuditLogger;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\Spaces;

uses(RefreshDatabase::class);

it('stores space and subject references', function () {
    $user = User::factory()->create();
    $spaceId = (string) Str::ulid();
    $subjectId = (string) Str::ulid();

    $log = app(AuditLogger::class)->record('record.secret_revealed', $user, $spaceId, 'record', $subjectId, ['field' => 'nid']);

    expect(AuditLog::query()->findOrFail($log->id))
        ->space_id->toBe($spaceId)
        ->subject_type->toBe('record')
        ->subject_id->toBe($subjectId)
        ->metadata->toBe(['field' => 'nid']);
});

it('rejects malformed actions', function (string $action) {
    app(AuditLogger::class)->record($action, null);
})->with(['login', 'Auth.Login', 'auth.', 'auth.login.extra', 'auth.log-in', 'auth.'.str_repeat('x', 80)])
    ->throws(InvalidArgumentException::class);

it('refuses sensitive metadata keys', function (string $key) {
    app(AuditLogger::class)->record('auth.login', null, metadata: [$key => 'x']);
})->with(['password', 'current_password', 'remember_token', 'Recovery_Code', 'two_factor_secret', 'code'])
    ->throws(InvalidArgumentException::class);

it('keeps entries when the actor is deleted', function () {
    $user = User::factory()->create();
    $log = app(AuditLogger::class)->record('auth.login', $user);

    // Account deletion (Privacy slice) removes the personal space first.
    Spaces::personalOf($user)->forceDelete();
    $user->delete();

    expect(AuditLog::query()->findOrFail($log->id)->actor_user_id)->toBeNull();
});
