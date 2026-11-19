<?php

namespace App\Modules\Identity\Models;

use App\Modules\Shared\Models\BaseModel;

/**
 * Append-only record of a user accepting (or withdrawing) a legal document.
 *
 * @property string $user_id
 * @property string $document
 * @property string $version
 * @property bool $granted
 */
class Consent extends BaseModel
{
    /**
     * @var list<string>
     */
    protected $fillable = ['document', 'version', 'granted', 'ip'];

    protected function casts(): array
    {
        return ['granted' => 'boolean'];
    }
}
