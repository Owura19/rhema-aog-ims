<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The checked scripture reference of a devotion: book, chapter and verse range (KJV).
 * Nullable: devotions saved before this have none until they are edited.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devotions', function (Blueprint $table) {
            $table->unsignedTinyInteger('bible_book_id')->nullable()->after('scripture_reference');
            $table->unsignedSmallInteger('chapter')->nullable()->after('bible_book_id');
            $table->unsignedSmallInteger('verse_start')->nullable()->after('chapter');
            $table->unsignedSmallInteger('verse_end')->nullable()->after('verse_start');

            $table->foreign('bible_book_id')->references('id')->on('bible_books')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('devotions', function (Blueprint $table) {
            $table->dropForeign(['bible_book_id']);
            $table->dropColumn(['bible_book_id', 'chapter', 'verse_start', 'verse_end']);
        });
    }
};
