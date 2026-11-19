<?php

namespace App\Modules\Shared\Recurrence;

use InvalidArgumentException;

/** An RRULE that is malformed or outside what LifeOS allows. $reason is a lang key suffix. */
final class InvalidRRule extends InvalidArgumentException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct("Invalid RRULE: {$reason}");
    }
}
