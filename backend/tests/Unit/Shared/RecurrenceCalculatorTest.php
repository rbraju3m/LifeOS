<?php

use App\Modules\Shared\Recurrence\RecurrenceCalculator;
use App\Modules\Shared\Recurrence\RRule;

function nextOf(string $rule, string $current, ?string $notBefore = null): ?array
{
    $next = (new RecurrenceCalculator)->next(RRule::parse($rule), $current, $notBefore ?? $current);

    return $next === null ? null : [$next['date'], $next['consumed'], $next['rule']?->toString()];
}

it('steps simple frequencies and intervals', function (string $rule, string $current, string $expected) {
    expect(nextOf($rule, $current)[0])->toBe($expected);
})->with([
    ['FREQ=DAILY', '2026-11-20', '2026-11-21'],
    ['FREQ=DAILY;INTERVAL=3', '2026-11-20', '2026-11-23'],
    ['FREQ=WEEKLY', '2026-11-20', '2026-11-27'],
    ['FREQ=WEEKLY;BYDAY=MO,TH', '2026-11-19', '2026-11-23'],        // Thu → Mon
    ['FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,TH', '2026-11-19', '2026-11-30'], // keeps the 2-week phase
    ['FREQ=MONTHLY', '2026-01-15', '2026-02-15'],
    ['FREQ=YEARLY', '2026-03-01', '2027-03-01'],
]);

it('handles month ends', function () {
    expect(nextOf('FREQ=MONTHLY;BYMONTHDAY=-1', '2026-01-31')[0])->toBe('2026-02-28')
        ->and(nextOf('FREQ=MONTHLY;BYMONTHDAY=-1', '2028-01-31')[0])->toBe('2028-02-29')
        // RFC 5545: months without a 31st are skipped, not clamped.
        ->and(nextOf('FREQ=MONTHLY;BYMONTHDAY=31', '2026-01-31')[0])->toBe('2026-03-31')
        ->and(nextOf('FREQ=MONTHLY;BYDAY=FR;BYSETPOS=-1', '2026-11-27')[0])->toBe('2026-12-25');
});

it('handles leap days', function () {
    expect(nextOf('FREQ=YEARLY', '2024-02-29')[0])->toBe('2028-02-29')
        ->and(nextOf('FREQ=YEARLY;BYMONTH=2;BYMONTHDAY=29', '2024-02-29')[0])->toBe('2028-02-29');
});

it('counts down COUNT and ends the series', function () {
    expect(nextOf('FREQ=DAILY;COUNT=3', '2026-11-20'))->toBe(['2026-11-21', 1, 'FREQ=DAILY;COUNT=2'])
        ->and(nextOf('FREQ=DAILY;COUNT=2', '2026-11-21'))->toBe(['2026-11-22', 1, 'FREQ=DAILY;COUNT=1'])
        ->and(nextOf('FREQ=DAILY;COUNT=1', '2026-11-22'))->toBeNull();
});

it('stops at UNTIL, inclusive, using only its date', function () {
    expect(nextOf('FREQ=DAILY;UNTIL=20261121', '2026-11-20')[0])->toBe('2026-11-21')
        ->and(nextOf('FREQ=DAILY;UNTIL=20261121', '2026-11-21'))->toBeNull()
        ->and(nextOf('FREQ=DAILY;UNTIL=20261121T080000Z', '2026-11-20')[0])->toBe('2026-11-21');
});

it('skips forward to today instead of creating a backlog, consuming COUNT', function () {
    expect(nextOf('FREQ=DAILY', '2026-11-01', '2026-11-20'))->toBe(['2026-11-20', 19, null])
        ->and(nextOf('FREQ=WEEKLY;BYDAY=MO', '2026-11-02', '2026-11-20'))->toBe(['2026-11-23', 3, null])
        ->and(nextOf('FREQ=DAILY;COUNT=10', '2026-11-01', '2026-11-05'))->toBe(['2026-11-05', 4, 'FREQ=DAILY;COUNT=6'])
        ->and(nextOf('FREQ=DAILY;COUNT=3', '2026-11-01', '2026-11-20'))->toBeNull();
});

it('continues from a date moved off the pattern', function () {
    // Weekly on Mondays, but this instance was moved to a Wednesday.
    expect(nextOf('FREQ=WEEKLY;BYDAY=MO', '2026-11-18')[0])->toBe('2026-11-23');
});
