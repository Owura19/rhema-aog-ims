<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->enum('type', [
                'Convention',
                'Crusade',
                'Wedding',
                'Funeral',
                'Dedication',
                'Anniversary',
                'Conference',
                'Outreach',
                'Youth Program',
                'Children Program',
                'Special Service',
                'Other'
            ])->default('Special Service');
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('venue')->nullable();
            $table->string('address')->nullable();
            $table->integer('capacity')->nullable();
            $table->decimal('ticket_price', 10, 2)->default(0);
            $table->boolean('is_free')->default(true);
            $table->boolean('rsvp_required')->default(false);
            $table->date('rsvp_deadline')->nullable();
            $table->string('banner_image')->nullable();
            $table->enum('status', [
                'Draft',
                'Published',
                'Ongoing',
                'Completed',
                'Cancelled'
            ])->default('Draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};