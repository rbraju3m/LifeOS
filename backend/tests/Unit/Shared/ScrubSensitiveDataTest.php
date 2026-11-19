<?php

use App\Modules\Shared\Logging\ScrubSensitiveData;
use App\Modules\Shared\Logging\SensitiveKeys;
use Monolog\Level;
use Monolog\LogRecord;

function scrub(string $message, array $context = [], array $extra = []): LogRecord
{
    return (new ScrubSensitiveData)(new LogRecord(new DateTimeImmutable, 'test', Level::Info, $message, $context, $extra));
}

it('redacts sensitive keys at any depth, case-insensitively', function () {
    $record = scrub('login', [
        'email' => 'nasrin@example.com',
        'Password' => 'hunter2hunter2',
        'input' => ['current_password' => 'x', 'name' => 'Nasrin', 'nested' => ['X-XSRF-TOKEN' => 'abc']],
        'headers' => ['authorization' => 'Bearer abc', 'cookie' => 'lifeos_session=abc'],
        'two_factor_recovery_codes' => ['a', 'b'],
        'request_id' => '01J0000000000000000000000',
        'count' => 3,
    ], ['remember_token' => 'r']);

    expect($record->context)->toBe([
        'email' => 'nasrin@example.com',
        'Password' => '[redacted]',
        'input' => ['current_password' => '[redacted]', 'name' => 'Nasrin', 'nested' => ['X-XSRF-TOKEN' => '[redacted]']],
        'headers' => ['authorization' => '[redacted]', 'cookie' => '[redacted]'],
        'two_factor_recovery_codes' => '[redacted]',
        'request_id' => '01J0000000000000000000000',
        'count' => 3,
    ])->and($record->extra)->toBe(['remember_token' => '[redacted]']);
});

it('masks bearer and basic credentials in the message and in string values', function () {
    $record = scrub('Upstream said: Authorization: Bearer eyJhbGciOi.abc-def_ghi', ['note' => 'sent basic dXNlcjpwYXNz']);

    expect($record->message)->toBe('Upstream said: Authorization: Bearer [redacted]')
        ->and($record->context['note'])->toBe('sent basic [redacted]');
});

it('leaves list items alone and stops at a depth limit', function () {
    $deep = ['a' => ['a' => ['a' => ['a' => ['a' => ['a' => ['a' => ['a' => ['a' => ['a' => 'x']]]]]]]]]];

    $record = scrub('m', ['list' => ['one', 'two'], 'deep' => $deep]);

    expect($record->context['list'])->toBe(['one', 'two'])
        ->and(json_encode($record->context['deep']))->toContain('[redacted]');
});

it('classifies keys', function (string $key, bool $sensitive) {
    expect(SensitiveKeys::isSensitive($key))->toBe($sensitive);
})->with([
    ['password', true],
    ['password_confirmation', true],
    ['new_password', true],
    ['api_token', true],
    ['client_secret', true],
    ['two_factor_secret', true],
    ['recovery_code', true],
    ['code', true],
    ['email', false],
    ['name', false],
    ['action', false],
    ['tokens_used', false],
]);
