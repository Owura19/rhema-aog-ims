<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Which chart-of-accounts account this transaction is posted to.
            // Nullable so existing rows don't break; new ones will set it.
            $table->foreignId('account_id')
                  ->nullable()
                  ->after('subcategory')
                  ->constrained('accounts')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_id');
        });
    }
};