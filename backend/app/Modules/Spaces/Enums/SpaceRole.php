<?php

namespace App\Modules\Spaces\Enums;

enum SpaceRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
    case Child = 'child';
    case Viewer = 'viewer';
}
