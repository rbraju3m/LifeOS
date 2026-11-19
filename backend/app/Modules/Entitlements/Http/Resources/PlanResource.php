<?php

namespace App\Modules\Entitlements\Http\Resources;

use App\Modules\Entitlements\Models\Feature;
use App\Modules\Entitlements\Models\Plan;
use App\Modules\Entitlements\Models\PlanPrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Plan
 */
class PlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->localizedName(),
            'space_type' => $this->space_type,
            'prices' => $this->prices->map(fn (PlanPrice $p) => [
                'country_code' => $p->country_code,
                'currency' => $p->currency,
                'interval' => $p->interval,
                'amount_minor' => $p->amount_minor,
            ])->values(),
            'features' => $this->features->mapWithKeys(fn (Feature $f) => [
                $f->code => $f->kind === 'boolean'
                    ? (bool) $f->getRelation('pivot')->getAttribute('bool_value')
                    : self::limit($f->getRelation('pivot')->getAttribute('limit_value')),
            ]),
        ];
    }

    private static function limit(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
