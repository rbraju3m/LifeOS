<?php

namespace App\Modules\Shared\Recurrence;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Complete-to-generate recurrence (docs/blueprint/10 §10.4), on dates only
 * (the rules allow nothing more frequent than daily). Callers apply the time
 * of day in the item's timezone afterwards.
 *
 * next() returns the first occurrence after $current that is on or after
 * $notBefore (usually "today" in the item's timezone), so completing a late
 * item does not produce a backlog. `consumed` counts the occurrences used up,
 * including the ones skipped, for COUNT bookkeeping.
 */
final class RecurrenceCalculator
{
    /** Safety limit on occurrences skipped in one step (≈ 27 years of daily). */
    private const MAX_SKIPPED = 10000;

    /**
     * `rule` is the rule to store on the next instance: COUNT reduced, or
     * null when it is unchanged. Null overall when the series has ended.
     *
     * @return array{date: string, consumed: int, rule: RRule|null}|null
     */
    public function next(RRule $rule, string $current, string $notBefore): ?array
    {
        $engine = $rule->engineRule($current);
        $utc = new DateTimeZone('UTC');
        $after = (new DateTimeImmutable($current, $utc))->add(new DateInterval('P1D'));
        $floor = new DateTimeImmutable($notBefore, $utc);

        $skipped = 0;
        if ($floor > $after) {
            $skipped = count($engine->getOccurrencesBetween($after, $floor->sub(new DateInterval('P1D')), self::MAX_SKIPPED + 1));
            if ($skipped > self::MAX_SKIPPED) {
                return null;
            }
        }

        $from = $floor > $after ? $floor : $after;
        $occurrence = $engine->getOccurrencesBetween($from, null, 1)[0] ?? null;
        if (! $occurrence instanceof DateTimeInterface) {
            return null;
        }

        $consumed = $skipped + 1;
        $count = $rule->count();
        if ($count !== null) {
            // COUNT on the current instance includes the current one.
            $remaining = $count - $consumed;
            if ($remaining < 1) {
                return null;
            }

            return ['date' => $occurrence->format('Y-m-d'), 'consumed' => $consumed, 'rule' => $rule->withCount($remaining)];
        }

        return ['date' => $occurrence->format('Y-m-d'), 'consumed' => $consumed, 'rule' => null];
    }

    /** Whether the rule has at least one occurrence after $current. */
    public function hasNext(RRule $rule, string $current): bool
    {
        return $this->next($rule, $current, $current) !== null;
    }
}
