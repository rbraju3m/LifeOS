<?php

use App\Modules\Shared\Database\Ulid;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// docs/blueprint/10-database-schema.md §10.2 — used from Phase 5 (FamilyOS);
// created now so the Spaces schema is final.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('space_invitations', function (Blueprint $table) {
            Ulid::primary($table);
            Ulid::foreign($table, 'space_id', 'spaces');
            $table->string('email', 254)->nullable();
            $table->string('phone_e164', 16)->nullable();
            $table->enum('role', ['admin', 'member', 'child', 'viewer'])->default('member'); // never 'owner'
            $table->char('token_hash', 64)->charset('ascii')->collation('ascii_bin')->unique(); // SHA-256 of the token
            Ulid::foreign($table, 'invited_by', 'users', 'set null', nullable: true);
            $table->dateTime('expires_at', 3);
            $table->dateTime('accepted_at', 3)->nullable();
            $table->dateTime('revoked_at', 3)->nullable();
            Ulid::foreign($table, 'accepted_user_id', 'users', 'set null', nullable: true);
            $table->dateTime('created_at', 3);
            $table->dateTime('updated_at', 3);

            $table->index(['space_id', 'email']);
        });

        DB::statement('ALTER TABLE space_invitations ADD CONSTRAINT chk_space_invitations_target CHECK (email IS NOT NULL OR phone_e164 IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('space_invitations');
    }
};
