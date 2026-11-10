<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One devotion per date.
 *
 * "One per date" is enforced by the database, but only among rows that are not
 * soft-deleted: live_publish_date is a STORED generated column that equals
 * publish_date while deleted_at is NULL and is NULL once the row is deleted.
 * MySQL lets any number of NULLs share a unique index, so deleted rows never
 * block their date, while two live rows on one date are refused.
 * (unique(publish_date, deleted_at) would not work: NULLs are distinct, so two
 * live rows — both deleted_at NULL — would pass.)
 *
 * verse_text is filled from the KJV dataset (E2), which also adds the parsed
 * book / chapter / verse columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devotions', function (Blueprint $table) {
            $table->id();
            $table->date('publish_date');
            $table->string('title');
            $table->string('scripture_reference');
            $table->text('verse_text')->nullable();
            $table->text('body');
            $table->text('prayer');
            $table->string('thought_for_the_day', 140);
            $table->foreignId('theme_id')->nullable()->constrained('devotion_themes')->nullOnDelete();
            $table->boolean('no_theme')->default(false);
            $table->string('author_name')->nullable();
            $table->enum('status', ['draft', 'approved'])->default('draft');
            $table->enum('source', ['manual', 'ai'])->default('manual');
            $table->boolean('ai_edited')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Must come after deleted_at. Read-only: MySQL computes it.
            $table->date('live_publish_date')->nullable()->storedAs('IF(`deleted_at` IS NULL, `publish_date`, NULL)');
            $table->unique('live_publish_date', 'devotions_one_live_per_date');

            $table->index(['status', 'publish_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devotions');
    }
};
