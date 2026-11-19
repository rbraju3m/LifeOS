<?php

namespace App\Modules\Entitlements\Models;

use App\Modules\Shared\Models\BaseModel;

/**
 * @property string $code
 * @property string $kind 'boolean' or 'limit'
 * @property array<string, string>|null $description
 */
class Feature extends BaseModel
{
    /**
     * @var list<string>
     */
    protected $fillable = [];

    protected function casts(): array
    {
        return ['description' => 'array'];
    }
}
