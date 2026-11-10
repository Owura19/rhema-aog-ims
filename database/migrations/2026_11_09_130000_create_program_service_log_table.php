<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which (programme, date) pairs the weekly-programme generator has already
 * handled. A row is never removed: if staff delete the generated service,
 * church_service_id becomes NULL but the row stays, so the date is never
 * recreated. New table only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_service_log', function (Blueprint $table) {
            $table->id();
            $table->string('program_key', 64);
            $table->date('service_date');
            $table->foreignId('church_service_id')->nullable()->constrained('church_services')->nullOnDelete();
            $table->enum('outcome', ['created', 'existing']);
            $table->timestamps();

            $table->unique(['program_key', 'service_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_service_log');
    }
};
