<?php

use App\Modules\Audit\AuditServiceProvider;
use App\Modules\Entitlements\EntitlementsServiceProvider;
use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Spaces\SpacesServiceProvider;
use App\Modules\Tags\TagsServiceProvider;
use App\Modules\Tasks\TasksServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    IdentityServiceProvider::class,
    SpacesServiceProvider::class,
    EntitlementsServiceProvider::class,
    AuditServiceProvider::class,
    TagsServiceProvider::class,
    TasksServiceProvider::class,
];
