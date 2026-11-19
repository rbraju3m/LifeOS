<?php

namespace App\Modules\Tags\Models;

use App\Modules\Shared\Models\BaseModel;
use App\Modules\Spaces\Contracts\SpaceOwnedContent;
use App\Modules\Spaces\Models\Concerns\BelongsToSpace;
use Illuminate\Support\Carbon;

/**
 * A label shared by everyone in a space (docs/blueprint/10 §10.3). Feature
 * modules link to tags through their own pivot tables (task_tag, note_tag, …).
 *
 * @property string $id
 * @property string $space_id
 * @property string|null $created_by
 * @property string $name
 * @property string|null $color
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Tag extends BaseModel implements SpaceOwnedContent
{
    use BelongsToSpace;

    /**
     * space_id and created_by are set by actions only.
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'color'];

    public static function contentType(): string
    {
        return 'tag';
    }

    /** Tags are always visible to the whole space. */
    public function hasVisibilityColumn(): bool
    {
        return false;
    }
}
