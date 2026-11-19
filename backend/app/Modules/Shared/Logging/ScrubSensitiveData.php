<?php

namespace App\Modules\Shared\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Monolog processor that redacts secrets from log records
 * (docs/blueprint/18 I5): values under sensitive keys in `context` and
 * `extra` (at any depth), and bearer tokens anywhere in the message or in
 * string values.
 */
final class ScrubSensitiveData implements ProcessorInterface
{
    public const REDACTED = '[redacted]';

    private const MAX_DEPTH = 8;

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->scrubString($record->message),
            context: $this->scrubArray($record->context, 0),
            extra: $this->scrubArray($record->extra, 0),
        );
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public function scrubArray(array $data, int $depth = 0): array
    {
        foreach ($data as $key => $value) {
            if (SensitiveKeys::isSensitive($key)) {
                $data[$key] = self::REDACTED;
            } elseif (is_array($value)) {
                $data[$key] = $depth >= self::MAX_DEPTH ? self::REDACTED : $this->scrubArray($value, $depth + 1);
            } elseif (is_string($value)) {
                $data[$key] = $this->scrubString($value);
            }
        }

        return $data;
    }

    private function scrubString(string $value): string
    {
        return (string) preg_replace('/\b(Bearer|Basic)\s+[A-Za-z0-9\-._~+\/|=]+/i', '$1 '.self::REDACTED, $value);
    }
}
