<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['Present', 'Late', 'Absent', 'Excused'])->default('Present');
            $table->enum('check_in_method', ['Biometric', 'Manual', 'Auto'])->default('Manual');
            $table->timestamp('check_in_time')->nullable();
            $table->integer('fingerprint_id')->nullable(); // ZKTeco punch ID
            $table->text('notes')->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // A member can only have one attendance record per service
            $table->unique(['church_service_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};