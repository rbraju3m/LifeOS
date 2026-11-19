<?php

use App\Modules\Identity\Enums\UserStatus;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires authentication', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized()->assertJson(['code' => 'unauthenticated']);
})->with([
    ['GET', '/api/v1/me'],
    ['PATCH', '/api/v1/me'],
    ['PATCH', '/api/v1/me/preferences'],
    ['POST', '/api/v1/auth/logout'],
    ['PUT', '/api/v1/auth/user/password'],
]);

it('returns the current user with preferences', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email_verified', true)
        ->assertJsonPath('data.two_factor_enabled', false)
        ->assertJsonPath('data.preferences.default_reminder_time', '09:00')
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.remember_token');
});

it('updates the profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patchJson('/api/v1/me', [
        'name' => 'Karim Uddin',
        'phone_e164' => '+8801712345678',
        'locale' => 'bn',
        'timezone' => 'Asia/Dubai',
        'country_code' => 'AE',
        'currency' => 'AED',
    ])->assertOk()
        ->assertJsonPath('data.name', 'Karim Uddin')
        ->assertJsonPath('data.timezone', 'Asia/Dubai');

    expect($user->fresh())
        ->phone_e164->toBe('+8801712345678')
        ->currency->toBe('AED');
});

it('rejects server-owned and unknown fields', function (string $field, mixed $value) {
    $user = User::factory()->create();

    $this->actingAs($user)->patchJson('/api/v1/me', [$field => $value])->assertJsonValidationErrors($field);

    expect($user->fresh()->status)->toBe(UserStatus::Active);
})->with([
    ['status', 'suspended'],
    ['email', 'new@example.com'],
    ['email_verified_at', '2026-01-01'],
    ['id', '01kaaaaaaaaaaaaaaaaaaaaaaa'],
    ['is_admin', true],
]);

it('validates profile fields', function (array $data, string $field) {
    $user = User::factory()->create();
    User::factory()->create(['phone_e164' => '+8801700000000']);

    $this->actingAs($user)->patchJson('/api/v1/me', $data)->assertJsonValidationErrors($field);
})->with([
    'phone not E.164' => [['phone_e164' => '01712345678'], 'phone_e164'],
    'phone taken' => [['phone_e164' => '+8801700000000'], 'phone_e164'],
    'bad timezone' => [['timezone' => 'Dhaka'], 'timezone'],
    'lower-case country' => [['country_code' => 'bd'], 'country_code'],
    'unsupported locale' => [['locale' => 'hi'], 'locale'],
]);

it('updates preferences', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patchJson('/api/v1/me/preferences', [
        'time_format' => '24h',
        'week_start' => 0,
        'use_bengali_digits' => true,
        'default_reminder_time' => '08:30',
        'quiet_hours_start' => '22:00',
        'quiet_hours_end' => '07:00',
    ])->assertOk()
        ->assertJsonPath('data.preferences.time_format', '24h')
        ->assertJsonPath('data.preferences.default_reminder_time', '08:30')
        ->assertJsonPath('data.preferences.quiet_hours_start', '22:00');
});

it('validates preferences', function (array $data, string $field) {
    $this->actingAs(User::factory()->create())
        ->patchJson('/api/v1/me/preferences', $data)
        ->assertJsonValidationErrors($field);
})->with([
    'week start out of range' => [['week_start' => 7], 'week_start'],
    'bad time' => [['default_reminder_time' => '25:00'], 'default_reminder_time'],
    'quiet hours half set' => [['quiet_hours_start' => '22:00'], 'quiet_hours_end'],
    'unknown field' => [['dashboard_config' => []], 'dashboard_config'],
]);

it("answers in the user's language", function () {
    $user = User::factory()->create(['locale' => 'bn']);

    $this->actingAs($user)->patchJson('/api/v1/me', ['status' => 'x'])
        ->assertJsonPath('title', 'আপনার দেওয়া কিছু তথ্য সঠিক নয়।');
});

it('uses Accept-Language for guests', function () {
    $this->withHeader('Accept-Language', 'bn-BD,bn;q=0.9,en;q=0.5')->getJson('/api/v1/me')
        ->assertJsonPath('title', 'আপনি সাইন ইন করেননি।');
});
