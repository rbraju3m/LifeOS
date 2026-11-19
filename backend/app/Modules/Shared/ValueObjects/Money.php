<?php

namespace App\Modules\Shared\ValueObjects;

use InvalidArgumentException;

/**
 * An amount in minor units (e.g. poisha, cents) plus an ISO 4217 currency.
 * Never use floats for money.
 */
final readonly class Money
{
    public function __construct(
        public int $amountMinor,
        public string $currency,
    ) {
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException("Invalid ISO 4217 currency code [{$currency}].");
        }
    }

    public static function of(int $amountMinor, string $currency): self
    {
        return new self($amountMinor, $currency);
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);
        $a = $this->amountMinor;
        $b = $other->amountMinor;

        // Check before adding: PHP silently turns an overflowing int sum into a float.
        if (($b > 0 && $a > PHP_INT_MAX - $b) || ($b < 0 && $a < PHP_INT_MIN - $b)) {
            throw new InvalidArgumentException('Money amount overflow.');
        }

        return new self($a + $b, $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->amountMinor === $other->amountMinor;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException("Currency mismatch: {$this->currency} vs {$other->currency}.");
        }
    }
}
