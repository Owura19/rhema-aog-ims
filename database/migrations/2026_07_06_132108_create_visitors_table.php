<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->enum('gender', ['Male', 'Female'])->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('address')->nullable();
            $table->string('occupation')->nullable();
            $table->enum('marital_status', ['Single', 'Married', 'Divorced', 'Widowed'])->nullable();
            $table->enum('how_heard', [
                'Friend/Family',
                'Social Media',
                'Flyer/Banner',
                'Radio/TV',
                'Walked In',
                'Online',
                'Other'
            ])->nullable();
            $table->foreignId('church_service_id')->nullable()->constrained()->nullOnDelete();
            $table->date('visit_date');
            $table->enum('visit_type', ['First Time', 'Second Time', 'Third Time', 'Regular'])->default('First Time');
            $table->enum('follow_up_status', [
                'Pending',
                'Called',
                'Visited',
                'Attended Again',
                'Joined',
                'No Response',
                'Not Interested'
            ])->default('Pending');
            $table->date('follow_up_date')->nullable();
            $table->text('follow_up_notes')->nullable();
            $table->foreignId('followed_up_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('converted_to_member')->default(false);
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitors');
    }
};