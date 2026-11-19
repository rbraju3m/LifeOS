<?php

namespace Tests\Fixtures\Spaces;

use App\Modules\Shared\Models\BaseModel;
use App\Modules\Spaces\Contracts\SpaceOwnedContent;
use App\Modules\Spaces\Models\Concerns\BelongsToSpace;

/**
 * A minimal space-owned content model for testing the Spaces authorization
 * core before real content modules exist. Its table is created by
 * FixtureNote::createTable() inside a test.
 */
class FixtureNote extends BaseModel implements SpaceOwnedContent
{
    use BelongsToSpace;

    protected $table = 'fixture_notes';

    /**
     * @var list<string>
     */
    protected $fillable = [];

    public static function contentType(): string
    {
        return 'note';
    }
}
