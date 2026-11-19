<?php

namespace App\Modules\Entitlements;

use InvalidArgumentException;

/**
 * The feature values that apply to one space right now.
 * Limits: int, or null for unlimited. Booleans: true/false.
 */
final readonly class ResolvedEntitlements
{
    /**
     * @param  array<string, bool|int|null>  $values
     */
    public function __construct(
        public string $planCode,
        public array $values,
    ) {}

    public function allows(string $feature): bool
    {
        $value = $this->get($feature);

        if (! is_bool($value)) {
            throw new InvalidArgumentException("Feature [{$feature}] is a limit, not a boolean.");
        }

        return $value;
    }

    /** null = unlimited. */
    public function limit(string $feature): ?int
    {
        $value = $this->get($feature);

        if (is_bool($value)) {
            throw new InvalidArgumentException("Feature [{$feature}] is a boolean, not a limit.");
        }

        return $value;
    }

    private function get(string $feature): bool|int|null
    {
        if (! array_key_exists($feature, $this->values)) {
            throw new InvalidArgumentException("Unknown feature [{$feature}].");
        }

        return $this->values[$feature];
    }
}
