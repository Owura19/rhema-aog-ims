<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('content')->nullable();
            $table->enum('type', ['text', 'photo', 'video', 'announcement'])->default('text');
            $table->string('media_path')->nullable();
            $table->string('media_thumbnail')->nullable();
            $table->enum('visibility', ['everyone', 'members', 'leaders'])->default('everyone');
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_approved')->default(true);
            $table->integer('likes_count')->default(0);
            $table->integer('comments_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};