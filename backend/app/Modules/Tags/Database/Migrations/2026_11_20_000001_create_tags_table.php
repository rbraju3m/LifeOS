<?php

use App\Modules\Shared\Database\Ulid;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| docs/blueprint/10 §10.3: tags are space-wide (no visibility column) and hard-deleted.
| The unique index uses the table collation (utf8mb4_0900_ai_ci), so "Bazar" and
| "bazar" are the same tag.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            Ulid::primary($table);
            Ulid::foreign($table, 'space_id', 'spaces');
            Ulid::foreign($table, 'created_by', 'users', 'set null', nullable: true);
            $table->string('name', 50);
            $table->char('color', 7)->nullable();
            $table->dateTime('created_at', 3);
            $table->dateTime('updated_at', 3);

            $table->unique(['space_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
