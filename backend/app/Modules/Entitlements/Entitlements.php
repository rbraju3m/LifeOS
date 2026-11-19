<?php

namespace App\Modules\Entitlements;

use App\Modules\Entitlements\Enums\SubscriptionStatus;
use App\Modules\Entitlements\Models\EntitlementOverride;
use App\Modules\Entitlements\Models\Feature;
use App\Modules\Entitlements\Models\Plan;
use App\Modules\Entitlements\Models\Subscription;
use App\Modules\Shared\Http\Problems\ApiProblemException;
use App\Modules\Spaces\Models\Space;
use Illuminate\Contracts\Cache\Repository as Cache;
use RuntimeException;

/**
 * Resolves what a space may do (docs/blueprint/17).
 *
 * Precedence: unexpired override > live subscription's plan > the default
 * plan for the space type. Features a plan does not list resolve to
 * false / 0. export.enabled is always true. Results are cached per space and
 * dropped when the space's subscription or overrides change; changes to
 * plan values themselves show up within CACHE_SECONDS.
 */
class Entitlements
{
    public const CACHE_SECONDS = 600;

    public function __construct(private readonly Cache $cache) {}

    public function for(Space $space): ResolvedEntitlements
    {
        /** @var array{plan: string, values: array<string, bool|int|null>} $data */
        $data = $this->cache->remember(self::cacheKey($space->id), $this->ttl($space), fn () => $this->resolve($space));

        return new ResolvedEntitlements($data['plan'], $data['values']);
    }

    /**
     * Blocks creating one more item when the space is at its limit. Reading,
     * editing, deleting and exporting existing data are never limited.
     *
     * @throws ApiProblemException 403 entitlement_exceeded
     */
    public function assertCanCreate(Space $space, string $feature, int $currentCount): void
    {
        $limit = $this->for($space)->limit($feature);

        if ($limit !== null && $currentCount >= $limit) {
            throw new ApiProblemException(403, 'entitlement_exceeded', meta: ['feature' => $feature, 'limit' => $limit]);
        }
    }

    public function forget(string $spaceId): void
    {
        $this->cache->forget(self::cacheKey($spaceId));
    }

    public static function cacheKey(string $spaceId): string
    {
        return 'entitlements:'.$spaceId;
    }

    /**
     * @return array{plan: string, values: array<string, bool|int|null>}
     */
    private function resolve(Space $space): array
    {
        $plan = $this->planFor($space);
        $planValues = $plan->features()->get()->mapWithKeys(fn (Feature $f) => [$f->code => $f->getRelation('pivot')]);

        $values = [];
        foreach (Feature::query()->get() as $feature) {
            $pivot = $planValues[$feature->code] ?? null;
            $values[$feature->code] = $feature->kind === 'boolean'
                ? (bool) ($pivot?->getAttribute('bool_value') ?? false)
                : ($pivot === null ? 0 : self::limitOrNull($pivot->getAttribute('limit_value')));
        }

        $overrides = EntitlementOverride::query()
            ->with('feature')
            ->where('space_id', $space->id)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderBy('created_at')
            ->get();

        foreach ($overrides as $override) {
            $values[$override->feature->code] = $override->feature->kind === 'boolean'
                ? (bool) $override->bool_value
                : $override->limit_value;
        }

        // Invariant (docs/blueprint/02): data export is never a paid feature.
        $values[FeatureCode::EXPORT_ENABLED] = true;

        return ['plan' => $plan->code, 'values' => $values];
    }

    private function planFor(Space $space): Plan
    {
        $subscription = Subscription::query()
            ->with('plan')
            ->where('space_id', $space->id)
            ->whereIn('status', SubscriptionStatus::LIVE)
            ->first();

        if ($subscription !== null) {
            return $subscription->plan;
        }

        return Plan::query()->where('space_type', $space->type->value)->where('is_default', true)->first()
            ?? throw new RuntimeException("No default plan for space type [{$space->type->value}].");
    }

    /** Never cache past the moment an override expires. */
    private function ttl(Space $space): int
    {
        $nextExpiry = EntitlementOverride::query()
            ->where('space_id', $space->id)
            ->where('expires_at', '>', now())
            ->min('expires_at');

        if ($nextExpiry === null) {
            return self::CACHE_SECONDS;
        }

        return max(1, min(self::CACHE_SECONDS, (int) now()->diffInSeconds($nextExpiry, true)));
    }

    private static function limitOrNull(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
