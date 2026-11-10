<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The KJV text for devotion verses. Created empty; filled by `php artisan bible:import-kjv`
 * (run once after migrating; see database/data/kjv/README.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bible_books', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();      // canonical position: Genesis 1 ... Revelation 66
            $table->char('code', 3)->unique();                 // USFM code, e.g. JHN
            $table->string('name', 32)->unique();
            $table->char('testament', 2);                      // OT or NT
            $table->unsignedSmallInteger('chapter_count');
            $table->unsignedSmallInteger('verse_count');
        });

        Schema::create('bible_verses', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('book_id');
            $table->unsignedSmallInteger('chapter');
            $table->unsignedSmallInteger('verse');
            $table->text('text');

            $table->foreign('book_id')->references('id')->on('bible_books')->restrictOnDelete();
            $table->unique(['book_id', 'chapter', 'verse'], 'bible_verses_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bible_verses');
        Schema::dropIfExists('bible_books');
    }
};
