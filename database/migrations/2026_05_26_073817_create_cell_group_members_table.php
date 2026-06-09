<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cell_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cell_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('Member'); // Member, Leader, Assistant Leader
            $table->date('joined_date')->nullable();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['cell_group_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cell_group_members');
    }
};