<?php

namespace App\Modules\Spaces\Http\Resources;

use App\Modules\Identity\Models\User;
use App\Modules\Spaces\Authorization\SpaceAccess;
use App\Modules\Spaces\Models\Space;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Space
 */
class SpaceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'name' => $this->name,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'my_role' => $user instanceof User ? app(SpaceAccess::class)->role($user, $this->resource)?->value : null,
            'created_at' => $this->created_at->toJSON(),
        ];
    }
}
