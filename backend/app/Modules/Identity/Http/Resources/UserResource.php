<?php

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in user. Never includes the password hash, remember token,
 * 2FA secret or recovery codes.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified' => $this->email_verified_at !== null,
            'phone_e164' => $this->phone_e164,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'country_code' => $this->country_code,
            'currency' => $this->currency,
            'two_factor_enabled' => $this->hasTwoFactorEnabled(),
            'status' => $this->status->value,
            'created_at' => $this->created_at->toJSON(),
            'preferences' => new PreferencesResource($this->whenLoaded('preferences')),
        ];
    }
}
