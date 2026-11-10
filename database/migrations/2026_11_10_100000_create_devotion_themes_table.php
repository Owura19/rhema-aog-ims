<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Themes the pastor sets for a run of dates; devotions (and AI drafts) follow the theme covering their date. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devotion_themes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devotion_themes');
    }
};
