<?php

use App\Modules\Shared\Database\Ulid;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// docs/blueprint/10 §10.4 task_lists [STD][SD][OWN].
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_lists', function (Blueprint $table) {
            Ulid::primary($table);
            Ulid::foreign($table, 'space_id', 'spaces');
            Ulid::foreign($table, 'created_by', 'users', 'set null', nullable: true);
            $table->enum('visibility', ['space', 'private'])->default('space');
            $table->string('name', 100);
            $table->char('color', 7)->nullable();
            $table->string('icon', 32)->nullable();
            $table->integer('sort_order')->default(0);
            $table->dateTime('archived_at', 3)->nullable();
            $table->dateTime('created_at', 3);
            $table->dateTime('updated_at', 3);
            $table->dateTime('deleted_at', 3)->nullable()->index();

            $table->index(['space_id', 'archived_at', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_lists');
    }
};
