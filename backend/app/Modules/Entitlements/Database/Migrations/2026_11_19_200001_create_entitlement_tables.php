<?php

use App\Modules\Shared\Database\Ulid;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| docs/blueprint/10-database-schema.md §10.8 and 17-subscription-and-feature-entitlement-design.md.
| Deviation: plans.is_default marks the plan a space falls back to when it
| has no live subscription (one per space_type: `free` and `free_family`).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            Ulid::primary($table);
            $table->string('code', 40)->unique();
            $table->json('name'); // {"en": "...", "bn": "..."}
            $table->enum('space_type', ['personal', 'family']);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_public')->default(true);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->dateTime('created_at', 3);
            $table->dateTime('updated_at', 3);
            // At most one default plan per space type.
            $table->string('default_key', 20)->nullable()
                ->storedAs('if(`is_default`, `space_type`, null)')
                ->unique();
        });

        Schema::create('plan_prices', function (Blueprint $table) {
            Ulid::primary($table);
            Ulid::foreign($table, 'plan_id', 'plans');
            $table->char('country_code', 2)->nullable(); // null = all countries
            $table->char('currency', 3);
            $table->enum('interval', ['month', 'year']);
            $table->unsignedBigInteger('amount_minor');
            $table->dateTime('active_from', 3);
            $table->dateTime('active_until', 3)->nullable();
            $table->dateTime('created_at', 3);
            $table->dateTime('updated_at', 3);

            $table->index(['plan_id', 'country_code', 'currency', 'interval', 'active_from'], 'plan_prices_lookup_index');
        });

        Schema::create('features', function (Blueprint $table) {
            Ulid::primary($table);
            $table->string('code', 80)->unique();
            $table->enum('kind', ['boolean', 'limit']);
            $table->json('description')->nullable();
            $table->dateTime('created_at', 3);
            $table->dateTime('updated_at', 3);
        });

        Schema::create('plan_features', function (Blueprint $table) {
            Ulid::foreign($table, 'plan_id', 'plans');
            Ulid::foreign($table, 'feature_id', 'features');
            $table->boolean('bool_value')->nullable();
            $table->bigInteger('limit_value')->nullable(); // null on a limit feature = unlimited
            $table->primary(['plan_id', 'feature_id']);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            Ulid::primary($table);
            // RESTRICT, not CASCADE: MySQL forbids cascading actions on a column
            // that a stored generated column (live_key) depends on. Purging a
            // space deletes its subscriptions explicitly first.
            Ulid::foreign($table, 'space_id', 'spaces', 'restrict');
            Ulid::foreign($table, 'plan_id', 'plans', 'restrict');
            $table->enum('status', ['active', 'trialing', 'past_due', 'cancelled', 'expired']);
            $table->enum('provider', ['none', 'manual', 'sslcommerz', 'bkash', 'stripe'])->default('none');
            $table->string('provider_ref', 255)->nullable();
            $table->dateTime('starts_at', 3);
            $table->dateTime('current_period_end', 3)->nullable();
            $table->dateTime('cancelled_at', 3)->nullable();
            $table->dateTime('created_at', 3);
            $table->dateTime('updated_at', 3);
            // At most one live (active, trialing or past_due) subscription per space.
            Ulid::column($table, 'live_key')->nullable()
                ->storedAs("if(`status` in ('active', 'trialing', 'past_due'), `space_id`, null)")
                ->unique();

            $table->index(['space_id', 'status']);
        });

        Schema::create('entitlement_overrides', function (Blueprint $table) {
            Ulid::primary($table);
            Ulid::foreign($table, 'space_id', 'spaces');
            Ulid::foreign($table, 'feature_id', 'features');
            $table->boolean('bool_value')->nullable();
            $table->bigInteger('limit_value')->nullable();
            $table->string('reason', 255);
            $table->dateTime('expires_at', 3)->nullable();
            Ulid::foreign($table, 'granted_by', 'users', 'set null', nullable: true);
            $table->dateTime('created_at', 3);
            $table->dateTime('updated_at', 3);

            $table->index(['space_id', 'feature_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entitlement_overrides');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('features');
        Schema::dropIfExists('plan_prices');
        Schema::dropIfExists('plans');
    }
};
