<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Complete-to-generate is idempotent per (recurrence_series_id, due_date)
| (docs/blueprint/10 §10.4): a series never gets two instances for the same
| date, even when completion races or is repeated. NULL series ids (tasks
| that do not recur) are not constrained.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['recurrence_series_id']);
            $table->unique(['recurrence_series_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropUnique(['recurrence_series_id', 'due_date']);
            $table->index('recurrence_series_id');
        });
    }
};
