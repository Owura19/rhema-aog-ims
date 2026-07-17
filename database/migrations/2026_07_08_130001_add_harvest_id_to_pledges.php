<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pledges', function (Blueprint $table) {
            $table->foreignId('harvest_id')
                  ->nullable()
                  ->after('pledge_purpose_id')
                  ->constrained('harvests')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pledges', function (Blueprint $table) {
            $table->dropConstrainedForeignId('harvest_id');
        });
    }
};