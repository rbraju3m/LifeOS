<?php

namespace App\Modules\Entitlements\Models;

use App\Modules\Entitlements\Enums\SubscriptionStatus;
use App\Modules\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $space_id
 * @property string $plan_id
 * @property SubscriptionStatus $status
 * @property-read Plan $plan
 */
class Subscription extends BaseModel
{
    /**
     * Changed only by billing actions (none in the MVP).
     *
     * @var list<string>
     */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'starts_at' => 'datetime',
            'current_period_end' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
