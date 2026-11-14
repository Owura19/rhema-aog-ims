<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sermons for the app's sermon library. Visible in the app when PUBLISHED (status
 * published and published_at reached); drafts and soft-deleted sermons are not.
 *
 * - preacher_name is free text; preacher_member_id optionally links a member.
 * - scripture_reference is optional; when given it was checked by ReferenceParser and
 *   bible_book_id / chapter / verse_start / verse_end hold the passage (no foreign key:
 *   sermons don't need the KJV text imported, and the parser only yields books 1–66).
 * - youtube_video_id is the 11-character id taken from a YouTube link.
 * - audio_url must be https. thumbnail_path is an uploaded image on the public disk;
 *   without one the app shows the YouTube thumbnail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sermons', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('preacher_name', 150);
            $table->foreignId('preacher_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->date('preached_on');

            $table->string('scripture_reference')->nullable();
            $table->unsignedTinyInteger('bible_book_id')->nullable();
            $table->unsignedSmallInteger('chapter')->nullable();
            $table->unsignedSmallInteger('verse_start')->nullable();
            $table->unsignedSmallInteger('verse_end')->nullable();

            $table->foreignId('series_id')->nullable()->constrained('sermon_series')->nullOnDelete();
            $table->text('summary')->nullable();
            $table->char('youtube_video_id', 11)->nullable();
            $table->string('audio_url', 500)->nullable();
            $table->string('thumbnail_path')->nullable();

            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'preached_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sermons');
    }
};
