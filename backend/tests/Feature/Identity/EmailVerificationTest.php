<?php

use App\Modules\Identity\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

it('verifies the email from the signed link', function () {
    $user = User::factory()->unverified()->create();
    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);

    $this->actingAs($user)->getJson($url)->assertNoContent();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('rejects a tampered link', function () {
    $user = User::factory()->unverified()->create();
    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $user->id,
        'hash' => sha1('someone-else@example.com'),
    ]);

    $this->actingAs($user)->getJson($url)->assertForbidden();
    $this->actingAs($user)->getJson(str_replace('signature=', 'signature=x', $url))->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('resends the verification email, at most 6 times an hour', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();
    $this->actingAs($user);

    foreach (range(1, 6) as $i) {
        $this->postJson('/api/v1/auth/email/verification-notification')->assertStatus(202);
    }
    $this->postJson('/api/v1/auth/email/verification-notification')->assertStatus(429);

    Notification::assertSentToTimes($user, VerifyEmail::class, 6);
});
