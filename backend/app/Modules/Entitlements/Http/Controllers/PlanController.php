<?php

namespace App\Modules\Entitlements\Http\Controllers;

use App\Modules\Entitlements\Http\Resources\PlanResource;
use App\Modules\Entitlements\Models\Plan;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlanController
{
    /** Public plan catalogue for the pricing page. */
    public function index(): AnonymousResourceCollection
    {
        $plans = Plan::query()
            ->where('is_public', true)
            ->where('is_active', true)
            ->with(['prices' => fn ($q) => $q->current()->orderBy('interval'), 'features'])
            ->orderBy('sort_order')
            ->get();

        return PlanResource::collection($plans);
    }
}
