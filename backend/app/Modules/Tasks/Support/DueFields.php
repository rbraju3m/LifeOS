<?php

namespace App\Modules\Tasks\Support;

use App\Modules\Shared\ValueObjects\LocalDateTime;
use App\Modules\Tasks\Models\Task;
use Carbon\CarbonImmutable;

/**
 * Turns the API's due fields (local `due_date`, optional `due_time`,
 * `timezone`) into the stored columns: due_date stays the local date and
 * due_at is the UTC instant when a time is set (docs/blueprint/10 §10.4).
 *
 * On update, due_date and due_time travel together: sending due_date without
 * due_time clears the time. Changing only the timezone keeps the local
 * date and time and recomputes due_at.
 */
final class DueFields
{
    /**
     * @param  array<string, mixed>  $input  validated request data
     * @return array{due_date: string|null, due_at: CarbonImmutable|null, timezone: string}
     */
    public static function resolve(array $input, string $defaultTimezone, ?Task $current = null): array
    {
        $timezone = is_string($input['timezone'] ?? null) ? $input['timezone'] : ($current->timezone ?? $defaultTimezone);

        if (array_key_exists('due_date', $input)) {
            $date = is_string($input['due_date']) ? $input['due_date'] : null;
            $time = is_string($input['due_time'] ?? null) ? $input['due_time'] : null;
        } else {
            $date = $current?->due_date?->format('Y-m-d');
            $time = $current !== null ? self::localTime($current) : null;
        }

        if ($date === null) {
            return ['due_date' => null, 'due_at' => null, 'timezone' => $timezone];
        }

        return [
            'due_date' => $date,
            'due_at' => $time !== null ? (new LocalDateTime("{$date} {$time}", $timezone))->toUtc() : null,
            'timezone' => $timezone,
        ];
    }

    /** The task's local due time (H:i), or null for date-only tasks. */
    public static function localTime(Task $task): ?string
    {
        if ($task->due_at === null) {
            return null;
        }

        return substr(LocalDateTime::fromUtc($task->due_at, $task->timezone)->wallClock, 11, 5);
    }
}
