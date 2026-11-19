<?php

namespace App\Modules\Shared\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Base for all domain models (docs/adr/0005-ulid-primary-keys.md):
 * ULID primary keys and millisecond-precision UTC timestamps (DATETIME(3)).
 */
abstract class BaseModel extends Model
{
    use HasUlids;

    protected $dateFormat = 'Y-m-d H:i:s.v';
}
