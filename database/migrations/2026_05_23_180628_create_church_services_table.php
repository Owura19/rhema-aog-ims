<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('church_services', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. Sunday Service, Midweek, Prayer Meeting
            $table->enum('service_type', [
                'Sunday Service',
                'Midweek Service',
                'Prayer Meeting',
                'Special Event',
                'Convention',
                'Other'
            ])->default('Sunday Service');
            $table->date('service_date');
            $table->time('start_time');
            $table->time('end_time')->nullable();
            $table->string('venue')->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['Scheduled', 'Ongoing', 'Completed', 'Cancelled'])->default('Scheduled');
            $table->boolean('biometric_enabled')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('church_services');
    }
};