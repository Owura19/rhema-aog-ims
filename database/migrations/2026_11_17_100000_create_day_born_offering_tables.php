<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Day-born offering: on a service day each day-born group (born on Sunday ... Saturday)
 * gives together; ushers record ONE total per group. Not per member.
 *
 *  - day_born_weeks: one row per service date (status, special service, optional finance link);
 *  - day_born_offerings: the 7 group totals of a week (unique per weekday);
 *  - day_born_offering_changes: the audit trail; rows are only ever added.
 *
 * A separate tally: it is NOT part of the finance totals unless a week is explicitly
 * "posted to finance" on publish (one linked Offering transaction).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('day_born_weeks', function (Blueprint $table) {
            $table->id();
            $table->date('service_date')->unique();
            $table->boolean('special_service')->default(false);   // a service on a day other than Sunday
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            // "Post to finance" (off by default): the one linked Offering transaction and its accounts.
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('finance_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('cash_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'service_date']);
        });

        Schema::create('day_born_offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('week_id')->constrained('day_born_weeks')->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');                 // 0 = Sunday ... 6 = Saturday
            $table->decimal('amount', 12, 2)->default(0);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['week_id', 'weekday']);
        });

        Schema::create('day_born_offering_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('week_id')->nullable()->constrained('day_born_weeks')->nullOnDelete();
            $table->date('service_date');                           // kept even if a draft week is deleted
            $table->unsignedTinyInteger('weekday')->nullable();     // null for week-level actions
            $table->string('action', 30);                           // created, updated, published, unpublished, posted_to_finance, deleted
            $table->decimal('old_amount', 12, 2)->nullable();
            $table->decimal('new_amount', 12, 2)->nullable();
            $table->string('reason', 500)->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['week_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('day_born_offering_changes');
        Schema::dropIfExists('day_born_offerings');
        Schema::dropIfExists('day_born_weeks');
    }
};
