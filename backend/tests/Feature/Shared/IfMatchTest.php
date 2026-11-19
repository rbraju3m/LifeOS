<?php

use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Content;
use Tests\Support\Spaces;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->space = Spaces::personalOf($this->user);
    $this->tag = Content::tag($this->space, $this->user, 'Bazar');
    $this->url = "/api/v1/spaces/{$this->space->id}/tags/{$this->tag->id}";
    $this->actingAs($this->user);
});

it('updates when If-Match carries the current version, in any ETag form', function (string $format) {
    $version = $this->getJson($this->url)->json('data.version');

    $this->withHeader('If-Match', sprintf($format, $version))
        ->patchJson($this->url, ['name' => 'Renamed'])->assertOk()->assertJsonPath('data.name', 'Renamed');
})->with(['%s', '"%s"', 'W/"%s"']);

it('answers 409 with the current version when If-Match is stale', function () {
    $stale = $this->getJson($this->url)->json('data.version');
    $this->travel(1)->seconds();
    $current = $this->patchJson($this->url, ['name' => 'First'])->assertOk()->json('data.version');

    $this->withHeader('If-Match', $stale)->patchJson($this->url, ['name' => 'Second'])
        ->assertConflict()
        ->assertJson(['code' => 'version_conflict', 'meta' => ['current_version' => $current]]);

    expect($this->tag->fresh()->name)->toBe('First');
});

it('allows updates without If-Match', function () {
    $this->patchJson($this->url, ['name' => 'Renamed'])->assertOk();
});
