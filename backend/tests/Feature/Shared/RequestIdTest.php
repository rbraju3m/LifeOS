<?php

use App\Modules\Shared\Http\Middleware\AssignRequestId;

it('assigns a ULID request id to every response', function () {
    $id = $this->getJson('/')->assertOk()->headers->get(AssignRequestId::HEADER);

    expect($id)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/');
});

it('reuses a safe incoming request id', function () {
    $this->getJson('/', [AssignRequestId::HEADER => 'edge-abc123.XYZ'])
        ->assertHeader(AssignRequestId::HEADER, 'edge-abc123.XYZ');
});

it('replaces an unsafe incoming request id', function (string $unsafe) {
    $id = $this->getJson('/', [AssignRequestId::HEADER => $unsafe])->headers->get(AssignRequestId::HEADER);

    expect($id)->not->toBe($unsafe)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/');
})->with([
    'log injection' => ["abc\nlevel=critical"],
    'too short' => ['abc'],
    'too long' => [str_repeat('a', 65)],
    'html' => ['<script>alert(1)</script>'],
]);
