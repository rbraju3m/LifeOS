<?php

namespace App\Modules\Spaces\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Shared\Models\BaseModel;
use App\Modules\Spaces\Enums\MemberStatus;
use App\Modules\Spaces\Enums\SpaceType;
use App\Modules\Spaces\Routing\SpaceContentRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * An account context that owns content: every user's personal space, or a
 * shared family space (docs/blueprint/09 §9.1).
 *
 * @property string $id
 * @property SpaceType $type
 * @property string $name
 * @property string $owner_user_id
 * @property string $timezone
 * @property string $currency
 * @property int $storage_used_bytes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Space extends BaseModel
{
    use SoftDeletes;

    /**
     * type and owner_user_id are set by actions only.
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'timezone', 'currency'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'timezone' => 'Asia/Dhaka',
        'currency' => 'BDT',
        'storage_used_bytes' => 0,
    ];

    protected function casts(): array
    {
        return [
            'type' => SpaceType::class,
            'storage_used_bytes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * @return HasMany<SpaceMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(SpaceMember::class);
    }

    /**
     * @return HasMany<SpaceMember, $this>
     */
    public function activeMembers(): HasMany
    {
        return $this->members()->where('status', MemberStatus::Active);
    }

    /**
     * Scoped route binding for content registered in SpaceContentRegistry:
     * the child is only found inside this space.
     *
     * @param  string  $childType
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveChildRouteBinding($childType, $value, $field): ?Model
    {
        $model = SpaceContentRegistry::modelFor($childType);

        if ($model === null) {
            return parent::resolveChildRouteBinding($childType, $value, $field);
        }

        $instance = new $model;

        return $instance->newQuery()
            ->where($instance->qualifyColumn('space_id'), $this->id)
            ->where($instance->qualifyColumn($field ?? $instance->getRouteKeyName()), $value)
            ->first();
    }

    public function isPersonal(): bool
    {
        return $this->type === SpaceType::Personal;
    }
}
