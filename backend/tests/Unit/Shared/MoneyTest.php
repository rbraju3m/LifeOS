<?php

use App\Modules\Shared\ValueObjects\Money;

it('adds amounts in the same currency', function () {
    expect(Money::of(9_900, 'BDT')->plus(Money::of(100, 'BDT'))->equals(Money::of(10_000, 'BDT')))->toBeTrue();
});

it('refuses to mix currencies', function () {
    Money::of(100, 'BDT')->plus(Money::of(100, 'USD'));
})->throws(InvalidArgumentException::class, 'Currency mismatch');

it('rejects invalid currency codes', function (string $code) {
    Money::of(1, $code);
})->throws(InvalidArgumentException::class)->with(['bdt', 'TK', 'BDTX', '']);

it('detects overflow instead of turning into a float', function () {
    Money::of(PHP_INT_MAX, 'BDT')->plus(Money::of(1, 'BDT'));
})->throws(InvalidArgumentException::class, 'overflow');
