<?php

use App\Modules\Shared\Database\Ulid;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Test-only table for Tests\Fixtures\Spaces\FixtureNote (the [OWN] column set).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixture_notes', function (Blueprint $table) {
            Ulid::primary($table);
            Ulid::foreign($table, 'space_id', 'spaces');
            Ulid::foreign($table, 'created_by', 'users', 'set null', nullable: true);
            $table->enum('visibility', ['space', 'private'])->default('space');
            $table->string('title');
            $table->dateTime('created_at', 3);
            $table->dateTime('updated_at', 3);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixture_notes');
    }
};
