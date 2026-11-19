<?php

namespace App\Modules\Audit\Models;

use App\Modules\Audit\AuditCategory;
use App\Modules\Audit\Casts\PackedIp;
use App\Modules\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One audit_logs row (docs/blueprint/10 §10.8). Append-only: rows are written
 * by AuditLogger and never changed. Only `audit:prune` deletes rows, through
 * the query builder, once they pass their retention period.
 *
 * @property string $id
 * @property string|null $actor_user_id
 * @property string|null $space_id
 * @property string $action
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string|null $ip
 * @property string|null $user_agent
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 */
class AuditLog extends BaseModel
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit log entries are append-only.'));
        static::deleting(fn () => throw new LogicException('Audit log entries are append-only.'));
    }

    protected function casts(): array
    {
        return [
            'ip' => PackedIp::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeSecurity(Builder $query): Builder
    {
        return $query->where('action', 'like', AuditCategory::SECURITY_PREFIX.'%');
    }
}
