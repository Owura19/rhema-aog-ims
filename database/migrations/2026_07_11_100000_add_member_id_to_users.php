<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Links a login account to a member record (for the member portal).
            // Nullable because staff accounts (admin, pastor) have no member link.
            $table->foreignId('member_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('members')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('member_id');
        });
    }
};