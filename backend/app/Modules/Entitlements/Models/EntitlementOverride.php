<?php

namespace App\Modules\Entitlements\Models;

use App\Modules\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A per-space exception to its plan (promotion, support grant, …).
 *
 * @property string $space_id
 * @property string $feature_id
 * @property bool|null $bool_value
 * @property int|null $limit_value
 * @property-read Feature $feature
 */
class EntitlementOverride extends BaseModel
{
    /**
     * @var list<string>
     */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'bool_value' => 'boolean',
            'limit_value' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Feature, $this>
     */
    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }
}
