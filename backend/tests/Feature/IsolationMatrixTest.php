<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Tests\Support\IsolationMatrix;

uses(RefreshDatabase::class);

/*
| docs/blueprint/19: every route that takes an ID, called by every actor that
| must not get in (or only partly). See tests/Support/IsolationMatrix.php.
*/

dataset('isolation cases', function () {
    foreach (array_keys(IsolationMatrix::routes()) as $route) {
        foreach (IsolationMatrix::ACTORS as $actor) {
            yield "{$route} as {$actor}" => [$route, $actor];
        }
    }
});

it('enforces isolation', function (string $route, string $actor) {
    $case = IsolationMatrix::arrange($route, $actor);
    if ($case === null) {
        expect(true)->toBeTrue(); // not applicable: the item type cannot be private

        return;
    }

    $definition = IsolationMatrix::routes()[$route];
    if ($case['actor'] !== null) {
        $this->actingAs($case['actor']);
    }

    $response = $this->json($definition['method'], route($route, $case['params']), $definition['body'] ?? []);

    expect($response->status())->toBe(
        IsolationMatrix::expected($route, $actor),
        "{$definition['method']} {$route} as {$actor}: ".$response->getContent(),
    );
})->with('isolation cases');

it('lists every API route that takes an ID', function () {
    $missing = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn (Route $r) => Str::startsWith($r->uri(), 'api/v1/') && $r->parameterNames() !== [])
        ->map(fn (Route $r) => $r->getName() ?? $r->uri())
        ->reject(fn (string $name) => array_key_exists($name, IsolationMatrix::routes())
            || in_array($name, IsolationMatrix::EXEMPT, true))
        ->values()
        ->all();

    expect($missing)->toBe([], 'Add these routes to tests/Support/IsolationMatrix.php: '.implode(', ', $missing));
});
