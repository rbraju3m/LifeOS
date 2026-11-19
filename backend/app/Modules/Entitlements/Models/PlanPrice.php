<?php

namespace App\Modules\Entitlements\Models;

use App\Modules\Shared\Models\BaseModel;
use App\Modules\Shared\ValueObjects\Money;
use Illuminate\Database\Eloquent\Builder;

/**
 * @property string|null $country_code
 * @property string $currency
 * @property string $interval
 * @property int $amount_minor
 */
class PlanPrice extends BaseModel
{
    /**
     * @var list<string>
     */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'active_from' => 'datetime',
            'active_until' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->where('active_from', '<=', now())
            ->where(fn (Builder $q) => $q->whereNull('active_until')->orWhere('active_until', '>', now()));
    }

    public function money(): Money
    {
        return Money::of($this->amount_minor, $this->currency);
    }
}
