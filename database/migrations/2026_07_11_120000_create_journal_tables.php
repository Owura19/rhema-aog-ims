<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A journal entry = one financial event (e.g. "GHS 500 tithe received").
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->date('entry_date');
            $table->string('description')->nullable();
            $table->string('reference')->nullable();           // human ref e.g. JE-0001

            // Link back to the source, so we can trace/rebuild.
            $table->string('source_type')->nullable();         // 'transaction', 'opening_balance', 'manual'
            $table->unsignedBigInteger('source_id')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
        });

        // Each journal line = one debit OR credit against one account.
        // A valid entry has >= 2 lines and total debits == total credits.
        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->timestamps();

            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
    }
};