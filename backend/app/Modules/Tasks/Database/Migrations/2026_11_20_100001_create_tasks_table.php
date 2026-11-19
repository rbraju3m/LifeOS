<?php

use App\Modules\Shared\Database\Ulid;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| docs/blueprint/10 §10.4 tasks [STD][SD][OWN] and the task_tag pivot.
|
| due_sort is a stored generated column so "sort by due" can use cursor
| pagination (which cannot page over NULLs): timed tasks sort at due_at,
| date-only tasks at 23:59:59 of due_date, undated tasks last.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            Ulid::primary($table);
            Ulid::foreign($table, 'space_id', 'spaces');
            Ulid::foreign($table, 'created_by', 'users', 'set null', nullable: true);
            $table->enum('visibility', ['space', 'private'])->default('space');
            Ulid::foreign($table, 'task_list_id', 'task_lists', 'set null', nullable: true);
            Ulid::foreign($table, 'assignee_user_id', 'users', 'set null', nullable: true);
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->enum('status', ['open', 'completed', 'cancelled'])->default('open');
            $table->unsignedTinyInteger('priority')->default(0);
            $table->date('due_date')->nullable();
            $table->dateTime('due_at', 3)->nullable();
            $table->string('timezone', 64);
            $table->string('rrule', 500)->nullable();
            Ulid::column($table, 'recurrence_series_id')->nullable();
            $table->dateTime('completed_at', 3)->nullable();
            Ulid::foreign($table, 'completed_by', 'users', 'set null', nullable: true);
            $table->integer('sort_order')->default(0);
            $table->dateTime('due_sort', 3)->storedAs("COALESCE(due_at, TIMESTAMP(due_date, '23:59:59'), '9999-12-31 23:59:59')");
            $table->dateTime('created_at', 3);
            $table->dateTime('updated_at', 3);
            $table->dateTime('deleted_at', 3)->nullable()->index();

            $table->index(['space_id', 'status', 'due_date']);
            $table->index(['space_id', 'status', 'due_at']);
            $table->index(['space_id', 'status', 'due_sort']);
            $table->index(['assignee_user_id', 'status']);
            $table->index('recurrence_series_id');
            $table->fullText(['title', 'description']);
        });

        DB::statement('ALTER TABLE tasks ADD CONSTRAINT chk_tasks_due CHECK (due_at IS NULL OR due_date IS NOT NULL)');

        Schema::create('task_tag', function (Blueprint $table) {
            Ulid::foreign($table, 'task_id', 'tasks');
            Ulid::foreign($table, 'tag_id', 'tags');
            $table->primary(['task_id', 'tag_id']);
            $table->index('tag_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_tag');
        Schema::dropIfExists('tasks');
    }
};
