<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('harvests', function (Blueprint $table) {
            $table->id();
            $table->string('name');                 // e.g. "2026 Annual Harvest"
            $table->year('year');
            $table->decimal('target_amount', 14, 2)->default(0);
            $table->date('harvest_date')->nullable(); // the December harvest Sunday
            $table->date('pledge_opens')->nullable(); // when pledging starts (e.g. August)
            $table->enum('status', ['Active', 'Completed', 'Cancelled'])->default('Active');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('harvests');
    }
};