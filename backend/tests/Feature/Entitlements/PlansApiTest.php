<?php

use App\Modules\Entitlements\Database\Seeders\EntitlementCatalogSeeder;
use App\Modules\Entitlements\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('lists the public plans with prices and limits, without signing in', function () {
    $response = $this->getJson('/api/v1/plans')->assertOk();

    expect($response->json('data.*.code'))->toBe(['free', 'personal_plus', 'free_family', 'family', 'family_pro'])
        ->and($response->json('data.1.prices'))->toBe([[
            'country_code' => 'BD', 'currency' => 'BDT', 'interval' => 'month', 'amount_minor' => 9900,
        ]])
        ->and($response->json('data.0.prices'))->toBe([])
        ->and($response->json('data.1.features')['records.max_count'])->toBeNull()
        ->and($response->json('data.0.features')['records.max_count'])->toBe(50);
});

it('localises plan names', function () {
    $this->withHeader('Accept-Language', 'bn')->getJson('/api/v1/plans')
        ->assertJsonPath('data.3.name', 'ফ্যামিলি');
});

it('hides inactive or non-public plans', function () {
    Plan::query()->where('code', 'family_pro')->update(['is_active' => false]);

    expect($this->getJson('/api/v1/plans')->json('data.*.code'))->not->toContain('family_pro');
});

it('has an idempotent catalogue seeder with exactly one default plan per space type', function () {
    (new EntitlementCatalogSeeder)->run();
    (new EntitlementCatalogSeeder)->run();

    expect(Plan::query()->count())->toBe(5)
        ->and(DB::table('plan_features')->count())->toBe(40)
        ->and(DB::table('plan_prices')->count())->toBe(3)
        ->and(Plan::query()->where('is_default', true)->pluck('code')->sort()->values()->all())->toBe(['free', 'free_family']);
});
