<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── PLEDGES ─────────────────────────────────────────────
        Schema::create('pledges', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique(); // e.g. PLG-00001

            // Pledger: either a linked member OR a free-text name (non-member)
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pledger_name')->nullable();

            // Purpose links to the manageable pledge_purposes list
            $table->foreignId('pledge_purpose_id')->constrained()->cascadeOnDelete();

            $table->decimal('amount_pledged', 12, 2);
            $table->string('currency')->default('GHS');

            $table->date('date_pledged');
            $table->date('target_date')->nullable(); // optional deadline to fulfil

            $table->enum('status', ['Active', 'Fulfilled', 'Cancelled'])->default('Active');
            $table->text('notes')->nullable();

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // ── PLEDGE PAYMENTS ─────────────────────────────────────
        Schema::create('pledge_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pledge_id')->constrained()->cascadeOnDelete();

            $table->decimal('amount', 12, 2);
            $table->date('payment_date');
            $table->enum('payment_method', [
                'Cash',
                'Mobile Money',
                'Bank Transfer',
                'Cheque',
                'Other'
            ])->default('Cash');

            // Link to the finance transaction this payment creates
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();

            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pledge_payments');
        Schema::dropIfExists('pledges');
    }
};