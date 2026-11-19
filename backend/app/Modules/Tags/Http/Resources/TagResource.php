<?php

namespace App\Modules\Tags\Http\Resources;

use App\Modules\Shared\Http\Concurrency\Version;
use App\Modules\Tags\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tag
 */
class TagResource extends JsonResource
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
            'created_by' => $this->created_by,
            'created_at' => $this->created_at->toJSON(),
            'updated_at' => $this->updated_at->toJSON(),
            'version' => Version::of($this->resource),
        ];
    }
}
