<?php

use App\Modules\Identity\Models\User;
use App\Modules\Shared\Http\Problems\ApiProblemException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware('api')->prefix('api/v1/_test')->group(function () {
        Route::post('/validate', fn (Request $r) => $r->validate(['name' => 'required|string']));
        Route::get('/auth', fn () => throw new AuthenticationException);
        Route::get('/model', fn () => throw (new ModelNotFoundException)->setModel(User::class, ['01J0000000000000000000000Z']));
        Route::get('/crash', fn () => throw new RuntimeException('SQLSTATE secret table users_private'));
        Route::get('/abort', fn () => abort(403, 'internal reason that must not leak'));
        Route::get('/throttle', fn () => throw new ThrottleRequestsException('x', null, ['Retry-After' => '30']));
        Route::get('/entitlement', fn () => throw new ApiProblemException(
            403, 'entitlement_exceeded', 'You can store up to 50 records on the Free plan.', ['feature' => 'records.max_count'],
        ));
    });
});

it('renders an unknown API route as a problem+json 404', function () {
    $response = $this->getJson('/api/v1/does-not-exist')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJson(['status' => 404, 'code' => 'not_found', 'detail' => null]);

    expect($response->json('type'))->toEndWith('/errors/not_found')
        ->and($response->json('request_id'))->toBe($response->headers->get('X-Request-Id'));
});

it('renders validation errors with field messages', function () {
    $this->postJson('/api/v1/_test/validate', [])
        ->assertUnprocessable()
        ->assertJson(['code' => 'validation_failed'])
        ->assertJsonStructure(['errors' => ['name']]);
});

it('renders unauthenticated as 401', function () {
    $this->getJson('/api/v1/_test/auth')->assertUnauthorized()->assertJson(['code' => 'unauthenticated']);
});

it('does not leak the model name on a missing record', function () {
    $response = $this->getJson('/api/v1/_test/model')->assertNotFound()->assertJson(['code' => 'not_found']);

    expect($response->getContent())->not->toContain('User')->not->toContain('No query results');
});

it('hides exception details on server errors when debug is off', function () {
    config(['app.debug' => false]);

    $response = $this->getJson('/api/v1/_test/crash')->assertStatus(500)->assertJson(['code' => 'server_error']);

    expect($response->getContent())->not->toContain('SQLSTATE')->not->toContain('debug');
});

it('does not copy abort() messages into the response', function () {
    $response = $this->getJson('/api/v1/_test/abort')->assertForbidden()->assertJson(['code' => 'forbidden', 'detail' => null]);

    expect($response->getContent())->not->toContain('internal reason');
});

it('keeps Retry-After on rate limit errors', function () {
    $this->getJson('/api/v1/_test/throttle')
        ->assertStatus(429)
        ->assertHeader('Retry-After', '30')
        ->assertJson(['code' => 'rate_limited']);
});

it('renders domain problems with their own code, detail and meta', function () {
    $this->getJson('/api/v1/_test/entitlement')
        ->assertForbidden()
        ->assertJson([
            'code' => 'entitlement_exceeded',
            'detail' => 'You can store up to 50 records on the Free plan.',
            'meta' => ['feature' => 'records.max_count'],
        ]);
});

it('localises error titles', function () {
    app()->setLocale('bn');

    $this->getJson('/api/v1/does-not-exist')->assertJson(['title' => 'আপনি যা খুঁজছেন তা পাওয়া যায়নি।']);
});

it('has the same error codes in English and Bengali', function () {
    $en = require lang_path('en/errors.php');
    $bn = require lang_path('bn/errors.php');

    expect(array_keys($bn))->toEqualCanonicalizing(array_keys($en));
});
