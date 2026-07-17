<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_messages', function (Blueprint $table) {
            $table->id();

            // Which member's conversation thread this message belongs to
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();

            // Who wrote this message: the member, or a leader (staff user)
            $table->enum('sender', ['member', 'leader']);

            // If a leader sent it, which user account (for attribution)
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->text('body');

            // Read tracking (so each side can see unread messages)
            $table->timestamp('read_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_messages');
    }
};