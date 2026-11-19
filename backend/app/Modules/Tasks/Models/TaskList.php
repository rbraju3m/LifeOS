<?php

namespace App\Modules\Tasks\Models;

use App\Modules\Shared\Models\BaseModel;
use App\Modules\Spaces\Contracts\SpaceOwnedContent;
use App\Modules\Spaces\Enums\Visibility;
use App\Modules\Spaces\Models\Concerns\BelongsToSpace;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $space_id
 * @property string|null $created_by
 * @property Visibility $visibility
 * @property string $name
 * @property string|null $color
 * @property string|null $icon
 * @property int $sort_order
 * @property Carbon|null $archived_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class TaskList extends BaseModel implements SpaceOwnedContent
{
    use BelongsToSpace, SoftDeletes;

    /**
     * space_id, created_by and archived_at are set by actions only.
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'color', 'icon', 'sort_order', 'visibility'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'visibility' => 'space',
        'sort_order' => 0,
        'color' => null,
        'icon' => null,
        'archived_at' => null,
        'deleted_at' => null,
    ];

    public static function contentType(): string
    {
        return 'task_list';
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
