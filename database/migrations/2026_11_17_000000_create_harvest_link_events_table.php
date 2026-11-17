<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who linked or unlinked harvest money to a pledge, and when. One row per action; rows are
 * never edited. source: 'review' (Harvests → Link payments) or 'finance_form' (Record /
 * Edit Transaction).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('harvest_link_events', function (Blueprint $table) {
            $table->id();
            $table->string('action', 10);                    // link | unlink
            $table->string('source', 20);                    // review | finance_form
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pledge_payment_id')->nullable()->constrained('pledge_payments')->nullOnDelete();
            $table->foreignId('pledge_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('harvest_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('giver_created')->default(false);  // link made a new giver pledge
            $table->boolean('member_claimed')->default(false); // link: name-only pledge given this member; unlink: that was undone
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['pledge_payment_id', 'action']);
            $table->index(['transaction_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('harvest_link_events');
    }
};
