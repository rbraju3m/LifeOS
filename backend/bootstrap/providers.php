<?php

use App\Modules\Audit\AuditServiceProvider;
use App\Modules\Entitlements\EntitlementsServiceProvider;
use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Spaces\SpacesServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    IdentityServiceProvider::class,
    SpacesServiceProvider::class,
    EntitlementsServiceProvider::class,
    AuditServiceProvider::class,
];
