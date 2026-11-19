<?php

use App\Modules\Shared\Database\Ulid;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| docs/blueprint/10-database-schema.md §10.1. Sessions live in Redis, so there
| is no sessions table.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            Ulid::primary($table);
            $table->string('name', 120);
            $table->string('email', 254)->unique();
            $table->dateTime('email_verified_at', 3)->nullable();
            $table->string('phone_e164', 16)->nullable()->unique();
            $table->dateTime('phone_verified_at', 3)->nullable();
            $table->string('password');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->dateTime('two_factor_confirmed_at', 3)->nullable();
            $table->string('locale', 10)->default('bn');
            $table->string('timezone', 64)->default('Asia/Dhaka');
            $table->char('country_code', 2)->default('BD');
            $table->char('currency', 3)->default('BDT');
            $table->enum('status', ['active', 'suspended', 'pending_deletion'])->default('active');
            $table->dateTime('deletion_requested_at', 3)->nullable();
            $table->dateTime('last_login_at', 3)->nullable();
            $table->rememberToken();
            $table->dateTime('created_at', 3);
            $table->dateTime('updated_at', 3);

            $table->index(['status', 'deletion_requested_at']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email', 254)->primary();
            $table->string('token');
            $table->dateTime('created_at', 3)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
