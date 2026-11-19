<?php

use Tests\Fixtures\Modules\Demo\DemoServiceProvider;

it('mounts module routes under /api/v1 with the api middleware group', function () {
    $this->app->register(DemoServiceProvider::class);

    $this->getJson('/api/v1/demo/ping')->assertOk()->assertJson(['data' => 'pong']);

    $route = collect(app('router')->getRoutes()->getRoutes())->first(fn ($r) => $r->uri() === 'api/v1/demo/ping');
    expect($route->gatherMiddleware())->toContain('api');
});
