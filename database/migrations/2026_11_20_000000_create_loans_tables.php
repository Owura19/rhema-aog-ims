<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Loans the church owes (e.g. to a credit union), repaid automatically from chosen
 * transaction types (default Project Offering + Harvest). See App\Services\Loans\LoanService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('lender');
            $table->text('description')->nullable();
            $table->foreignId('liability_account_id')->constrained('accounts');
            $table->decimal('opening_balance', 14, 2);
            $table->date('opening_date');
            $table->foreignId('opening_counter_account_id')->constrained('accounts');    // usually 3200 Opening Balance Equity
            $table->foreignId('opening_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->date('repayment_start_date');
            $table->json('counted_types');                                               // e.g. ["Project Offering","Harvest"]
            $table->string('repayment_source', 10)->default('received');                 // received | fixed
            $table->foreignId('repayment_account_id')->nullable()->constrained('accounts'); // when fixed, e.g. 1536 C/U Harvest
            $table->string('status', 10)->default('Active');                             // Active | Paid off
            $table->date('paid_off_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // One row per counted transaction (unique: never counted twice, by any loan).
        Schema::create('loan_repayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->unique()->constrained();
            $table->decimal('transaction_amount', 14, 2);
            $table->decimal('amount_applied', 14, 2);
            $table->decimal('surplus', 14, 2)->default(0);                              // part not applied (last payment)
            $table->foreignId('cash_account_id')->nullable()->constrained('accounts');    // the account credited
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->string('flag', 30)->nullable();                                      // no_ledger_entry
            $table->timestamps();
        });

        // Manual changes, with a reason. Never edited or deleted.
        Schema::create('loan_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 12);                                                  // correction | repayment
            $table->decimal('amount', 14, 2);                                            // + owed more, − owed less
            $table->date('adjustment_date');
            $table->text('reason');
            $table->foreignId('counter_account_id')->constrained('accounts');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // What happened to a loan, and who did it.
        Schema::create('loan_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->string('action', 30);
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 14, 2)->nullable();
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_events');
        Schema::dropIfExists('loan_adjustments');
        Schema::dropIfExists('loan_repayments');
        Schema::dropIfExists('loans');
    }
};
