<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->year('year');
            $table->decimal('amount', 14, 2)->default(0);
            $table->timestamps();

            // One budget figure per account per year
            $table->unique(['account_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};