<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();        // e.g. 4100, 4101
            $table->string('ref')->nullable();       // e.g. A1, A1-i  (church-familiar ref)
            $table->string('name');                  // e.g. General Offering
            $table->enum('type', [
                'Income',
                'Expense',
                'Asset',
                'Liability',
                'Equity',
            ]);
            // Self-referencing parent for the group -> sub-account hierarchy
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->boolean('is_group')->default(false); // true for A1/B1 headings
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);   // preserves display order
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};