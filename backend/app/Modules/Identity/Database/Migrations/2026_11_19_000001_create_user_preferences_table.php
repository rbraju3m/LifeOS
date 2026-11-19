<?php

use App\Modules\Shared\Database\Ulid;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// docs/blueprint/10-database-schema.md §10.1 — one row per user (1:1).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $table) {
            Ulid::column($table, 'user_id')->primary();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('date_format', 20)->default('DD/MM/YYYY');
            $table->enum('time_format', ['12h', '24h'])->default('12h');
            $table->unsignedTinyInteger('week_start')->default(6); // 0 = Sunday, 6 = Saturday
            $table->boolean('use_bengali_digits')->default(false);
            $table->time('default_reminder_time')->default('09:00:00');
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();
            $table->enum('theme', ['system', 'light', 'dark'])->default('system');
            $table->json('dashboard_config')->nullable();
            $table->dateTime('created_at', 3);
            $table->dateTime('updated_at', 3);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
