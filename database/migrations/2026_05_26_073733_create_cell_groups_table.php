<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cell_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique()->nullable(); // e.g. CG-001
            $table->enum('type', ['Cell Group', 'Department', 'Ministry', 'Team'])->default('Cell Group');
            $table->text('description')->nullable();
            $table->string('meeting_day')->nullable(); // e.g. Monday
            $table->time('meeting_time')->nullable();
            $table->string('meeting_venue')->nullable();
            $table->foreignId('leader_id')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignId('assistant_leader_id')->nullable()->constrained('members')->nullOnDelete();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cell_groups');
    }
};