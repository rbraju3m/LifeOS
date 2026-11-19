<?php

use App\Modules\Shared\Database\Ulid;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// docs/blueprint/10-database-schema.md §10.2
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spaces', function (Blueprint $table) {
            Ulid::primary($table);
            $table->enum('type', ['personal', 'family']);
            $table->string('name', 120);
            // RESTRICT: a user who owns a space cannot be deleted until the
            // space is deleted or ownership is transferred.
            Ulid::foreign($table, 'owner_user_id', 'users', 'restrict');
            // Exactly one personal space per user.
            Ulid::column($table, 'personal_owner_key')->nullable()
                ->storedAs("if(`type` = 'personal', `owner_user_id`, null)")
                ->unique();
            $table->string('timezone', 64)->default('Asia/Dhaka');
            $table->char('currency', 3)->default('BDT');
            $table->unsignedBigInteger('storage_used_bytes')->default(0);
            $table->dateTime('created_at', 3);
            $table->dateTime('updated_at', 3);
            $table->dateTime('deleted_at', 3)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spaces');
    }
};
