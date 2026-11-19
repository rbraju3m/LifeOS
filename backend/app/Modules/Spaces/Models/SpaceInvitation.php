<?php

namespace App\Modules\Spaces\Models;

use App\Modules\Shared\Models\BaseModel;

/**
 * Invitation to join a family space. Table only until Phase 5 (FamilyOS).
 * The token itself is never stored, only its SHA-256 hash.
 */
class SpaceInvitation extends BaseModel
{
    /**
     * @var list<string>
     */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
