<?php

namespace Tests\Fixtures\Spaces;

use App\Modules\Spaces\Authorization\SpaceContentPolicy;

class FixtureNotePolicy extends SpaceContentPolicy
{
    protected function contentType(): string
    {
        return 'note';
    }
}
