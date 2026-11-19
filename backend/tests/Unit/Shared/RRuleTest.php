<?php

use App\Modules\Shared\Recurrence\InvalidRRule;
use App\Modules\Shared\Recurrence\RRule;

it('accepts supported rules and returns a canonical form', function (string $input, string $canonical) {
    expect(RRule::parse($input)->toString())->toBe($canonical);
})->with([
    ['FREQ=DAILY', 'FREQ=DAILY'],
    ['rrule:freq=weekly;byday=mo,th;interval=2', 'FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,TH'],
    ['FREQ=MONTHLY;BYMONTHDAY=-1;COUNT=12', 'FREQ=MONTHLY;COUNT=12;BYMONTHDAY=-1'],
    ['FREQ=MONTHLY;BYDAY=FR;BYSETPOS=-1', 'FREQ=MONTHLY;BYDAY=FR;BYSETPOS=-1'],
    ['FREQ=YEARLY;BYMONTH=2;BYMONTHDAY=29;UNTIL=20400101', 'FREQ=YEARLY;UNTIL=20400101;BYMONTH=2;BYMONTHDAY=29'],
    ['FREQ=WEEKLY;UNTIL=20261231T235959Z;WKST=SU', 'FREQ=WEEKLY;UNTIL=20261231T235959Z;WKST=SU'],
    ['FREQ=MONTHLY;BYDAY=2MO', 'FREQ=MONTHLY;BYDAY=2MO'],
]);

it('rejects rules LifeOS does not allow', function (string $input, string $reason) {
    expect(fn () => RRule::parse($input))->toThrow(fn (InvalidRRule $e) => expect($e->reason)->toBe($reason));
})->with([
    'empty' => ['', 'length'],
    'too long' => ['FREQ=DAILY;BYDAY='.implode(',', array_fill(0, 200, 'MO')), 'length'],
    'hourly' => ['FREQ=HOURLY', 'freq'],
    'no freq' => ['INTERVAL=2', 'freq'],
    'byhour' => ['FREQ=DAILY;BYHOUR=9', 'unsupported_part'],
    'dtstart' => ['DTSTART=20260101;FREQ=DAILY', 'unsupported_part'],
    'interval 0' => ['FREQ=DAILY;INTERVAL=0', 'interval'],
    'count 1001' => ['FREQ=DAILY;COUNT=1001', 'count'],
    'count and until' => ['FREQ=DAILY;COUNT=2;UNTIL=20270101', 'count_and_until'],
    'bad until' => ['FREQ=DAILY;UNTIL=2027-01-01', 'until'],
    'impossible until' => ['FREQ=DAILY;UNTIL=20270231', 'until'],
    'bad byday' => ['FREQ=WEEKLY;BYDAY=XX', 'byday'],
    'monthday 0' => ['FREQ=MONTHLY;BYMONTHDAY=0', 'bymonthday'],
    'monthday 32' => ['FREQ=MONTHLY;BYMONTHDAY=32', 'bymonthday'],
    'month 13' => ['FREQ=YEARLY;BYMONTH=13', 'bymonth'],
    'duplicate part' => ['FREQ=DAILY;FREQ=WEEKLY', 'syntax'],
    'no value' => ['FREQ=', 'syntax'],
]);
