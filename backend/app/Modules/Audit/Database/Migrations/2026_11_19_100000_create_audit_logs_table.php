<?php

use App\Modules\Shared\Database\Ulid;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| docs/blueprint/10 §10.8 audit_logs: append-only, so there is no updated_at.
| space_id has no foreign key because entries must outlive the space.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            Ulid::primary($table);
            Ulid::foreign($table, 'actor_user_id', 'users', 'set null', nullable: true);
            Ulid::column($table, 'space_id')->nullable();
            $table->string('action', 80);
            $table->string('subject_type', 40)->nullable();
            Ulid::column($table, 'subject_id')->nullable();
            $table->binary('ip', 16)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('created_at', 3);

            $table->index(['actor_user_id', 'created_at']);
            $table->index(['space_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
