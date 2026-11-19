<?php

namespace App\Modules\Spaces\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Shared\Models\BaseModel;
use App\Modules\Spaces\Enums\MemberStatus;
use App\Modules\Spaces\Enums\SpaceRole;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $space_id
 * @property string $user_id
 * @property SpaceRole $role
 * @property MemberStatus $status
 * @property Carbon $joined_at
 * @property-read Space $space
 */
class SpaceMember extends BaseModel
{
    /**
     * Nothing is mass-assignable: membership is only changed by actions.
     *
     * @var list<string>
     */
    protected $fillable = [];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'invited_by' => null,
        'removed_at' => null,
    ];

    protected function casts(): array
    {
        return [
            'role' => SpaceRole::class,
            'status' => MemberStatus::class,
            'joined_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Space, $this>
     */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
