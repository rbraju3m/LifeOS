<?php

namespace App\Modules\Spaces\Enums;

enum MemberStatus: string
{
    case Active = 'active';
    case Removed = 'removed';
}
