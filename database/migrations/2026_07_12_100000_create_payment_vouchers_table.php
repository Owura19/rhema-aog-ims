<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_no')->unique();          // e.g. PV-00001
            $table->date('voucher_date');
            $table->string('payee');                          // who is being paid
            $table->text('description');                      // what it's for

            // Accounting classification
            $table->enum('category', ['Expense', 'Asset']);
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();      // the expense/asset account
            $table->foreignId('cash_account_id')->nullable()->constrained('accounts')->nullOnDelete(); // paid from (cash/bank)

            $table->decimal('amount', 15, 2);
            $table->string('payment_method')->default('Cash'); // Cash, Cheque, Bank Transfer, Mobile Money
            $table->string('cheque_number')->nullable();

            // Workflow status
            $table->enum('status', ['Pending', 'Approved', 'Paid', 'Rejected', 'Cancelled'])->default('Pending');

            // Approval tracking (physical signatures happen on the printout; we log who marked each step)
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();

            // Link to the posted transaction once paid
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_vouchers');
    }
};