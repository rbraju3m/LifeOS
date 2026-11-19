<?php

namespace App\Modules\Tasks\Enums;

enum TaskStatus: string
{
    case Open = 'open';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
