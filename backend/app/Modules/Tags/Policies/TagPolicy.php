<?php

namespace App\Modules\Tags\Policies;

use App\Modules\Spaces\Authorization\SpaceContentPolicy;

class TagPolicy extends SpaceContentPolicy
{
    protected function contentType(): string
    {
        return 'tag';
    }
}
