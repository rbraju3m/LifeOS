<?php

namespace App\Modules\Shared\Recurrence;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use RRule\RRule as LibraryRRule;
use Throwable;

/**
 * A validated RFC 5545 recurrence rule without DTSTART (docs/blueprint/10,
 * 12 §12.5). The item's own due date is the start.
 *
 * Allowed: FREQ (DAILY, WEEKLY, MONTHLY, YEARLY, so never more often than
 * daily), INTERVAL 1–366, COUNT 1–1000 or UNTIL (not both), BYDAY,
 * BYMONTHDAY, BYMONTH, BYSETPOS, WKST. UNTIL may be a date (YYYYMMDD) or a
 * UTC date-time; only its date is used. toString() returns a canonical form.
 */
final readonly class RRule
{
    public const MAX_LENGTH = 500;

    public const MAX_COUNT = 1000;

    private const FREQS = ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'];

    private const ORDER = ['FREQ', 'INTERVAL', 'COUNT', 'UNTIL', 'BYMONTH', 'BYMONTHDAY', 'BYDAY', 'BYSETPOS', 'WKST'];

    private const DAY = '(MO|TU|WE|TH|FR|SA|SU)';

    /**
     * @param  array<string, string>  $parts  canonical, validated parts
     */
    private function __construct(private array $parts) {}

    public static function parse(string $rule): self
    {
        $rule = strtoupper(trim($rule));
        if (str_starts_with($rule, 'RRULE:')) {
            $rule = substr($rule, 6);
        }

        if ($rule === '' || strlen($rule) > self::MAX_LENGTH) {
            throw new InvalidRRule('length');
        }

        $parts = [];
        foreach (explode(';', rtrim($rule, ';')) as $pair) {
            $kv = explode('=', $pair, 2);
            if (count($kv) !== 2 || $kv[1] === '') {
                throw new InvalidRRule('syntax');
            }
            [$key, $value] = $kv;
            if (! in_array($key, self::ORDER, true)) {
                throw new InvalidRRule('unsupported_part');
            }
            if (isset($parts[$key])) {
                throw new InvalidRRule('syntax');
            }
            $parts[$key] = $value;
        }

        self::validate($parts);

        $canonical = [];
        foreach (self::ORDER as $key) {
            if (isset($parts[$key])) {
                $canonical[$key] = $parts[$key];
            }
        }

        $instance = new self($canonical);
        $instance->assertUnderstoodByEngine();

        return $instance;
    }

    public function toString(): string
    {
        return implode(';', array_map(fn ($k, $v) => "{$k}={$v}", array_keys($this->parts), $this->parts));
    }

    public function count(): ?int
    {
        return isset($this->parts['COUNT']) ? (int) $this->parts['COUNT'] : null;
    }

    public function withCount(int $count): self
    {
        $parts = $this->parts;
        $parts['COUNT'] = (string) $count;
        $ordered = [];
        foreach (self::ORDER as $key) {
            if (isset($parts[$key])) {
                $ordered[$key] = $parts[$key];
            }
        }

        return new self($ordered);
    }

    /**
     * The rule for the recurrence engine, starting on $date (Y-m-d) at UTC
     * midnight, without COUNT (the caller tracks it).
     *
     * @return LibraryRRule&iterable<DateTimeInterface>
     */
    public function engineRule(string $date): LibraryRRule
    {
        $utc = new DateTimeZone('UTC');
        $parts = $this->parts;
        unset($parts['COUNT'], $parts['UNTIL']);

        $engineParts = [...$parts, 'DTSTART' => new DateTimeImmutable($date.' 00:00:00', $utc)];
        if (isset($this->parts['UNTIL'])) {
            $engineParts['UNTIL'] = new DateTimeImmutable(substr($this->parts['UNTIL'], 0, 8).' 00:00:00', $utc);
        }

        return new LibraryRRule($engineParts);
    }

    /**
     * @param  array<string, string>  $parts
     */
    private static function validate(array $parts): void
    {
        if (! in_array($parts['FREQ'] ?? '', self::FREQS, true)) {
            throw new InvalidRRule('freq');
        }
        if (isset($parts['INTERVAL']) && ! self::intIn($parts['INTERVAL'], 1, 366)) {
            throw new InvalidRRule('interval');
        }
        if (isset($parts['COUNT']) && ! self::intIn($parts['COUNT'], 1, self::MAX_COUNT)) {
            throw new InvalidRRule('count');
        }
        if (isset($parts['COUNT'], $parts['UNTIL'])) {
            throw new InvalidRRule('count_and_until');
        }
        if (isset($parts['UNTIL']) && preg_match('/^\d{8}(T\d{6}Z)?$/', $parts['UNTIL']) !== 1) {
            throw new InvalidRRule('until');
        }
        if (isset($parts['UNTIL']) && ! checkdate((int) substr($parts['UNTIL'], 4, 2), (int) substr($parts['UNTIL'], 6, 2), (int) substr($parts['UNTIL'], 0, 4))) {
            throw new InvalidRRule('until');
        }
        if (isset($parts['BYDAY']) && ! self::listMatches($parts['BYDAY'], '/^([+-]?([1-9]|[1-4]\d|5[0-3]))?'.self::DAY.'$/')) {
            throw new InvalidRRule('byday');
        }
        if (isset($parts['BYMONTHDAY']) && ! self::intList($parts['BYMONTHDAY'], 31, signed: true)) {
            throw new InvalidRRule('bymonthday');
        }
        if (isset($parts['BYMONTH']) && ! self::intList($parts['BYMONTH'], 12, signed: false)) {
            throw new InvalidRRule('bymonth');
        }
        if (isset($parts['BYSETPOS']) && ! self::intList($parts['BYSETPOS'], 366, signed: true)) {
            throw new InvalidRRule('bysetpos');
        }
        if (isset($parts['WKST']) && preg_match('/^'.self::DAY.'$/', $parts['WKST']) !== 1) {
            throw new InvalidRRule('wkst');
        }
    }

    private function assertUnderstoodByEngine(): void
    {
        try {
            $this->engineRule('2026-01-01');
        } catch (Throwable) {
            throw new InvalidRRule('syntax');
        }
    }

    private static function intIn(string $value, int $min, int $max): bool
    {
        return preg_match('/^\d{1,4}$/', $value) === 1 && (int) $value >= $min && (int) $value <= $max;
    }

    private static function listMatches(string $value, string $pattern): bool
    {
        foreach (explode(',', $value) as $item) {
            if (preg_match($pattern, $item) !== 1) {
                return false;
            }
        }

        return true;
    }

    private static function intList(string $value, int $max, bool $signed): bool
    {
        foreach (explode(',', $value) as $item) {
            if (preg_match($signed ? '/^[+-]?\d{1,3}$/' : '/^\d{1,2}$/', $item) !== 1) {
                return false;
            }
            $n = abs((int) $item);
            if ($n < 1 || $n > $max) {
                return false;
            }
        }

        return true;
    }
}
