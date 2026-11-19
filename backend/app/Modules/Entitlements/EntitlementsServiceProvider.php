<?php

namespace App\Modules\Entitlements;

use App\Modules\Entitlements\Models\EntitlementOverride;
use App\Modules\Entitlements\Models\Subscription;
use App\Modules\Shared\Providers\ModuleServiceProvider;

class EntitlementsServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        // Drop a space's cached entitlements as soon as its plan or overrides change.
        $forget = fn (Subscription|EntitlementOverride $model) => $this->app->make(Entitlements::class)->forget($model->space_id);
        Subscription::saved($forget);
        Subscription::deleted($forget);
        EntitlementOverride::saved($forget);
        EntitlementOverride::deleted($forget);
    }
}
