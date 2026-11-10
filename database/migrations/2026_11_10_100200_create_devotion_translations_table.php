<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One translation per devotion per language (config/devotions.php locales).
 * Text fields may be empty while a translation is a draft. source_hash records the
 * English text it was made from, so a later English edit can mark it out of date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devotion_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('devotion_id')->constrained('devotions')->cascadeOnDelete();
            $table->string('locale', 8);
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->text('prayer')->nullable();
            $table->string('thought_for_the_day', 140)->nullable();
            $table->text('verse_text')->nullable();
            $table->enum('status', ['draft', 'approved'])->default('draft');
            $table->boolean('ai_drafted')->default(false);
            $table->string('source_hash', 64)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['devotion_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devotion_translations');
    }
};
