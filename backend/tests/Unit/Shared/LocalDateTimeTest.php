<?php

use App\Modules\Shared\ValueObjects\LocalDateTime;
use Carbon\CarbonImmutable;

it('converts Dhaka wall-clock time to UTC', function () {
    expect((new LocalDateTime('2026-11-18 09:00', 'Asia/Dhaka'))->toUtc()->toIso8601ZuluString())
        ->toBe('2026-11-18T03:00:00Z');
});

it('round-trips from UTC', function () {
    $local = LocalDateTime::fromUtc(CarbonImmutable::parse('2026-11-18T03:00:00Z'), 'Asia/Dhaka');

    expect($local->wallClock)->toBe('2026-11-18 09:00:00');
});

it('moves a non-existent spring-forward time forward', function () {
    // 02:30 does not exist in New York on 2026-03-08; it becomes 03:30 EDT.
    expect((new LocalDateTime('2026-03-08 02:30', 'America/New_York'))->toUtc()->toIso8601ZuluString())
        ->toBe('2026-03-08T07:30:00Z');
});

it('uses the first occurrence of an ambiguous fall-back time', function () {
    // 01:30 happens twice in New York on 2026-11-01; the first is EDT (UTC-4).
    expect((new LocalDateTime('2026-11-01 01:30', 'America/New_York'))->toUtc()->toIso8601ZuluString())
        ->toBe('2026-11-01T05:30:00Z');
});

it('rejects invalid dates and times', function (string $value) {
    new LocalDateTime($value, 'Asia/Dhaka');
})->throws(InvalidArgumentException::class)->with(['2026-02-30 09:00', '2026-11-18 24:00', '2026-11-18', 'tomorrow 9am']);

it('rejects offsets and unknown zones in place of IANA names', function (string $zone) {
    new LocalDateTime('2026-11-18 09:00', $zone);
})->throws(InvalidArgumentException::class)->with(['+06:00', 'BST', 'Asia/Nowhere']);
