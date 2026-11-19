<?php

namespace App\Modules\Shared\ValueObjects;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

/**
 * A wall-clock time as the user meant it ("09:00 in Asia/Dhaka"), kept
 * separate from the UTC instant it maps to (docs/blueprint/10 §10.10).
 *
 * DST rules when converting to UTC: a time that does not exist (spring-forward
 * gap) moves forward by the gap length; an ambiguous time (fall-back) uses the
 * first occurrence.
 */
final readonly class LocalDateTime
{
    public string $wallClock;

    public function __construct(string $wallClock, public string $timezone)
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?$/', $wallClock, $m) !== 1
            || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])
            || (int) $m[4] > 23 || (int) $m[5] > 59 || (int) ($m[6] ?? 0) > 59) {
            throw new InvalidArgumentException("Invalid local date-time [{$wallClock}].");
        }

        if (! in_array($timezone, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) {
            throw new InvalidArgumentException("Invalid IANA time zone [{$timezone}].");
        }

        $this->wallClock = sprintf('%s-%s-%s %s:%s:%s', $m[1], $m[2], $m[3], $m[4], $m[5], $m[6] ?? '00');
    }

    public static function fromUtc(DateTimeInterface $instant, string $timezone): self
    {
        $local = CarbonImmutable::instance($instant)->setTimezone($timezone);

        return new self($local->format('Y-m-d H:i:s'), $timezone);
    }

    public function toUtc(): CarbonImmutable
    {
        $local = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $this->wallClock, $this->timezone);

        if (! $local instanceof CarbonImmutable) {
            throw new InvalidArgumentException("Cannot resolve [{$this->wallClock}] in [{$this->timezone}].");
        }

        return $local->utc();
    }
}
