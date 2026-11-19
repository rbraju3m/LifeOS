<?php

use App\Modules\Shared\Database\Ulid;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// docs/blueprint/10-database-schema.md §10.2 — users ↔ spaces (M:N).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('space_members', function (Blueprint $table) {
            Ulid::primary($table);
            Ulid::foreign($table, 'space_id', 'spaces');
            Ulid::foreign($table, 'user_id', 'users');
            $table->enum('role', ['owner', 'admin', 'member', 'child', 'viewer'])->default('member');
            $table->enum('status', ['active', 'removed'])->default('active');
            Ulid::foreign($table, 'invited_by', 'users', 'set null', nullable: true);
            $table->dateTime('joined_at', 3);
            $table->dateTime('removed_at', 3)->nullable();
            $table->dateTime('created_at', 3);
            $table->dateTime('updated_at', 3);

            $table->unique(['space_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('space_members');
    }
};
