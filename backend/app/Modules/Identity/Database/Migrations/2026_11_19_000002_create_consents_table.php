<?php

use App\Modules\Shared\Database\Ulid;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// docs/blueprint/10-database-schema.md §10.1 — append-only legal acceptance history.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consents', function (Blueprint $table) {
            Ulid::primary($table);
            Ulid::foreign($table, 'user_id', 'users');
            $table->enum('document', ['terms', 'privacy', 'marketing']);
            $table->string('version', 20);
            $table->boolean('granted');
            $table->binary('ip', 16)->nullable();
            $table->dateTime('created_at', 3);
            $table->dateTime('updated_at', 3);

            $table->index(['user_id', 'document', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
    }
};
