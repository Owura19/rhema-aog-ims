<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_relationships', function (Blueprint $table) {
            $table->id();

            // The member the relationship is defined from
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();

            // The related member
            $table->foreignId('related_member_id')->constrained('members')->cascadeOnDelete();

            // Relationship of related_member TO member.
            // e.g. type = 'child' means related_member is the CHILD of member.
            $table->enum('type', [
                'spouse',
                'parent',
                'child',
                'sibling',
                'guardian',
                'other',
            ]);

            $table->string('label')->nullable(); // optional free note, e.g. "step-mother"
            $table->timestamps();

            // Prevent duplicate identical links
            $table->unique(['member_id', 'related_member_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_relationships');
    }
};