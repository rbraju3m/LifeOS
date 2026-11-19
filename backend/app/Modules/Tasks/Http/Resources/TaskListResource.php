<?php

namespace App\Modules\Tasks\Http\Resources;

use App\Modules\Shared\Http\Concurrency\Version;
use App\Modules\Tasks\Models\TaskList;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TaskList
 */
class TaskListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'space_id' => $this->space_id,
            'name' => $this->name,
            'color' => $this->color,
            'icon' => $this->icon,
            'sort_order' => $this->sort_order,
            'visibility' => $this->visibility->value,
            'created_by' => $this->created_by,
            'archived_at' => $this->archived_at?->toJSON(),
            'created_at' => $this->created_at->toJSON(),
            'updated_at' => $this->updated_at->toJSON(),
            'deleted_at' => $this->deleted_at?->toJSON(),
            'version' => Version::of($this->resource),
        ];
    }
}
