<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique(); // e.g. TXN-00001
            $table->enum('type', [
                'Tithe',
                'Offering',
                'First Fruit',
                'Seed',
                'Pledge',
                'Donation',
                'Expense',
                'Other'
            ]);
            $table->enum('category', [
                'Income',
                'Expense'
            ])->default('Income');
            $table->decimal('amount', 12, 2);
            $table->string('currency')->default('GHS');
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payer_name')->nullable(); // for anonymous/walk-in
            $table->date('transaction_date');
            $table->foreignId('church_service_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('payment_method', [
                'Cash',
                'Mobile Money',
                'Bank Transfer',
                'Cheque',
                'Other'
            ])->default('Cash');
            $table->string('mobile_money_number')->nullable();
            $table->string('cheque_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->enum('status', ['Pending', 'Confirmed', 'Cancelled'])->default('Confirmed');
            $table->text('description')->nullable();
            $table->string('receipt_number')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};