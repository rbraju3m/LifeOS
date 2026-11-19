<?php

use App\Modules\Entitlements\Database\Seeders\EntitlementCatalogSeeder;
use Illuminate\Database\Migrations\Migration;

// Reference data every environment needs (including tests): plans, features
// and their values. Later value changes go through new migrations or an admin tool.
return new class extends Migration
{
    public function up(): void
    {
        (new EntitlementCatalogSeeder)->run();
    }

    public function down(): void
    {
        // Removed together with the tables by the previous migration's down().
    }
};
