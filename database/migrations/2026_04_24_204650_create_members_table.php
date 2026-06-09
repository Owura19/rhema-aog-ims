<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('member_id')->unique(); // e.g. GW-00001
            $table->string('first_name');
            $table->string('last_name');
            $table->string('other_name')->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('phone')->nullable();
            $table->string('alt_phone')->nullable();
            $table->enum('gender', ['Male', 'Female']);
            $table->date('date_of_birth')->nullable();
            $table->string('occupation')->nullable();
            $table->string('employer')->nullable();
            $table->enum('marital_status', ['Single', 'Married', 'Divorced', 'Widowed'])->default('Single');
            $table->string('residential_address')->nullable();
            $table->string('digital_address')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->date('date_joined')->nullable();
            $table->date('date_baptized')->nullable();
            $table->enum('membership_status', ['Active', 'Inactive', 'Visitor', 'Transferred', 'Deceased'])->default('Active');
            $table->enum('member_type', ['Full Member', 'Associate', 'Visitor'])->default('Full Member');
            $table->string('photo')->nullable();
            $table->unsignedBigInteger('family_id')->nullable();
            $table->string('family_role')->nullable(); // Head, Spouse, Child
            $table->integer('fingerprint_id')->nullable(); // ZKTeco device user ID
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};