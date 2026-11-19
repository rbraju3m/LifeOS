<?php

namespace App\Modules\Entitlements\Database\Seeders;

use App\Modules\Entitlements\FeatureCode as F;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Plans, features and values from docs/blueprint/17. All prices and limits
 * are hypotheses; this is data, so changing them never needs a code change.
 * Idempotent: safe to run again (upserts by code).
 */
class EntitlementCatalogSeeder extends Seeder
{
    private const MB = 1024 * 1024;

    private const GB = 1024 * 1024 * 1024;

    /** code => [space_type, is_default, sort, en name, bn name] */
    private const PLANS = [
        'free' => ['personal', true, 10, 'Free', 'ফ্রি'],
        'personal_plus' => ['personal', false, 20, 'Personal Plus', 'পার্সোনাল প্লাস'],
        'free_family' => ['family', true, 30, 'Free Family', 'ফ্রি ফ্যামিলি'],
        'family' => ['family', false, 40, 'Family', 'ফ্যামিলি'],
        'family_pro' => ['family', false, 50, 'Family Pro', 'ফ্যামিলি প্রো'],
    ];

    /** code => [kind, en description, bn description] */
    private const FEATURES = [
        F::STORAGE_MAX_BYTES => ['limit', 'File storage', 'ফাইল সংরক্ষণের জায়গা'],
        F::FILES_MAX_UPLOAD_BYTES => ['limit', 'Maximum size of one file', 'একটি ফাইলের সর্বোচ্চ আকার'],
        F::SPACE_MAX_MEMBERS => ['limit', 'Members per space', 'প্রতি স্পেসে সদস্য সংখ্যা'],
        F::SPACE_MAX_FAMILY_SPACES => ['limit', 'Family spaces you can create', 'আপনি যতগুলো ফ্যামিলি স্পেস খুলতে পারবেন'],
        F::RECORDS_MAX_COUNT => ['limit', 'Personal records', 'ব্যক্তিগত রেকর্ড'],
        F::REMINDERS_MAX_ACTIVE => ['limit', 'Active reminders', 'চালু রিমাইন্ডার'],
        F::REMINDERS_SMS_PER_MONTH => ['limit', 'SMS reminders per month', 'প্রতি মাসে SMS রিমাইন্ডার'],
        F::EXPORT_ENABLED => ['boolean', 'Export your data', 'আপনার তথ্য এক্সপোর্ট'],
    ];

    /**
     * Plan values. A limit of null means unlimited. Features not listed for a
     * plan resolve to false / 0.
     *
     * @return array<string, array<string, int|bool|null>>
     */
    private static function values(): array
    {
        return [
            'free' => [
                F::STORAGE_MAX_BYTES => 500 * self::MB, F::FILES_MAX_UPLOAD_BYTES => 10 * self::MB,
                F::SPACE_MAX_MEMBERS => 1, F::SPACE_MAX_FAMILY_SPACES => 1, F::RECORDS_MAX_COUNT => 50,
                F::REMINDERS_MAX_ACTIVE => 500, F::REMINDERS_SMS_PER_MONTH => 0, F::EXPORT_ENABLED => true,
            ],
            'personal_plus' => [
                F::STORAGE_MAX_BYTES => 5 * self::GB, F::FILES_MAX_UPLOAD_BYTES => 25 * self::MB,
                F::SPACE_MAX_MEMBERS => 1, F::SPACE_MAX_FAMILY_SPACES => 1, F::RECORDS_MAX_COUNT => null,
                F::REMINDERS_MAX_ACTIVE => null, F::REMINDERS_SMS_PER_MONTH => 30, F::EXPORT_ENABLED => true,
            ],
            'free_family' => [
                F::STORAGE_MAX_BYTES => 500 * self::MB, F::FILES_MAX_UPLOAD_BYTES => 10 * self::MB,
                F::SPACE_MAX_MEMBERS => 3, F::SPACE_MAX_FAMILY_SPACES => 1, F::RECORDS_MAX_COUNT => 50,
                F::REMINDERS_MAX_ACTIVE => 500, F::REMINDERS_SMS_PER_MONTH => 0, F::EXPORT_ENABLED => true,
            ],
            'family' => [
                F::STORAGE_MAX_BYTES => 10 * self::GB, F::FILES_MAX_UPLOAD_BYTES => 25 * self::MB,
                F::SPACE_MAX_MEMBERS => 6, F::SPACE_MAX_FAMILY_SPACES => 1, F::RECORDS_MAX_COUNT => null,
                F::REMINDERS_MAX_ACTIVE => null, F::REMINDERS_SMS_PER_MONTH => 60, F::EXPORT_ENABLED => true,
            ],
            'family_pro' => [
                F::STORAGE_MAX_BYTES => 50 * self::GB, F::FILES_MAX_UPLOAD_BYTES => 50 * self::MB,
                F::SPACE_MAX_MEMBERS => 12, F::SPACE_MAX_FAMILY_SPACES => 3, F::RECORDS_MAX_COUNT => null,
                F::REMINDERS_MAX_ACTIVE => null, F::REMINDERS_SMS_PER_MONTH => 200, F::EXPORT_ENABLED => true,
            ],
        ];
    }

    /** Bangladesh monthly prices in poisha (৳99 = 9900). */
    private const BD_MONTHLY_PRICES = ['personal_plus' => 9900, 'family' => 19900, 'family_pro' => 39900];

    public function run(): void
    {
        $now = now()->format('Y-m-d H:i:s.v');

        foreach (self::PLANS as $code => [$spaceType, $isDefault, $sort, $en, $bn]) {
            DB::table('plans')->upsert([[
                'id' => $this->idFor('plans', $code), 'code' => $code,
                'name' => json_encode(['en' => $en, 'bn' => $bn], JSON_UNESCAPED_UNICODE),
                'space_type' => $spaceType, 'is_default' => $isDefault, 'is_public' => true, 'is_active' => true,
                'sort_order' => $sort, 'created_at' => $now, 'updated_at' => $now,
            ]], ['code'], ['name', 'space_type', 'is_default', 'sort_order', 'updated_at']);
        }

        foreach (self::FEATURES as $code => [$kind, $en, $bn]) {
            DB::table('features')->upsert([[
                'id' => $this->idFor('features', $code), 'code' => $code, 'kind' => $kind,
                'description' => json_encode(['en' => $en, 'bn' => $bn], JSON_UNESCAPED_UNICODE),
                'created_at' => $now, 'updated_at' => $now,
            ]], ['code'], ['kind', 'description', 'updated_at']);
        }

        $planIds = DB::table('plans')->pluck('id', 'code');
        $features = DB::table('features')->get(['id', 'code', 'kind'])->keyBy('code');

        foreach (self::values() as $planCode => $values) {
            foreach ($values as $featureCode => $value) {
                $feature = $features->get($featureCode) ?? throw new RuntimeException("Feature [{$featureCode}] is not in the catalogue.");
                DB::table('plan_features')->upsert([[
                    'plan_id' => $planIds[$planCode], 'feature_id' => $feature->id,
                    'bool_value' => $feature->kind === 'boolean' ? (bool) $value : null,
                    'limit_value' => $feature->kind === 'limit' ? $value : null,
                ]], ['plan_id', 'feature_id'], ['bool_value', 'limit_value']);
            }
        }

        foreach (self::BD_MONTHLY_PRICES as $planCode => $amount) {
            $exists = DB::table('plan_prices')->where([
                'plan_id' => $planIds[$planCode], 'country_code' => 'BD', 'currency' => 'BDT', 'interval' => 'month',
            ])->whereNull('active_until')->exists();

            if (! $exists) {
                DB::table('plan_prices')->insert([
                    'id' => strtolower((string) Str::ulid()), 'plan_id' => $planIds[$planCode], 'country_code' => 'BD',
                    'currency' => 'BDT', 'interval' => 'month', 'amount_minor' => $amount,
                    'active_from' => $now, 'active_until' => null, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    private function idFor(string $table, string $code): string
    {
        $existing = DB::table($table)->where('code', $code)->value('id');

        return is_string($existing) ? $existing : strtolower((string) Str::ulid());
    }
}
