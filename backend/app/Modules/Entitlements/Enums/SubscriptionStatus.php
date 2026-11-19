<?php

namespace App\Modules\Entitlements\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case Trialing = 'trialing';
    case PastDue = 'past_due';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    /** Statuses whose plan applies (at most one per space, enforced by a unique key). */
    public const LIVE = ['active', 'trialing', 'past_due'];
}
